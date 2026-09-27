<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;

/**
 * Imports cleaning/database/database.sqlite (legacy cleaning-side data) into
 * the merged application's live schema. Root's mysqldump backup is imported
 * separately, via import_root_backup.sh (plain mysql CLI, no PHP) — keeping
 * that out of this command avoids the cross-connection lock contention we
 * hit when both lived in the same Laravel transaction.
 *
 * Safe to run more than once: every inserted row is recorded in
 * `legacy_import_map` (source + table + old id -> new id), and reruns skip
 * anything already mapped instead of re-inserting it.
 *
 * Usage:
 *   php artisan legacy:import-cleaning --sqlite=/path/to/database.sqlite
 *   php artisan legacy:import-cleaning --sqlite=/path/to/database.sqlite --dry-run
 */
class ImportCleaningLegacyData extends Command
{
    protected $signature = 'legacy:import-cleaning
        {--sqlite= : Path to cleaning database.sqlite backup}
        {--dry-run : Run everything inside a transaction and roll it back at the end}';

    protected $description = 'Import legacy cleaning-side sqlite data into the merged schema, idempotently';

    protected PDO $sqlite;
    protected array $idMap = [];
    protected array $describeCache = [];

    public function handle(): int
    {
        $sqlitePath = $this->option('sqlite');
        if (!$sqlitePath || !file_exists($sqlitePath)) {
            $this->error("Pass a valid --sqlite path. Got: " . ($sqlitePath ?: '(none)'));
            return self::FAILURE;
        }

        $this->sqlite = new PDO('sqlite:' . $sqlitePath);
        $this->sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->ensureMapTable();
        $this->loadExistingMap();

        $dryRun = (bool) $this->option('dry-run');
        DB::beginTransaction();

        try {
            $this->importUsers();
            $this->importRolesAndPermissions();
            $this->importProperties();
            $this->importSimple('rooms', [], ['name']);
            $this->importChild('room_photos', 'rooms', 'room_id');
            $this->importPivot('property_room', 'properties', 'property_id', 'rooms', 'room_id');
            $this->importSimple('tasks', [], ['name']);
            $this->importPivot('room_task', 'rooms', 'room_id', 'tasks', 'task_id');
            $this->importChild('task_media', 'tasks', 'task_id', ['type', 'url', 'path']);
            $this->importPivot('property_tasks', 'properties', 'property_id', 'tasks', 'task_id');
            $this->importSimple('property_checkouts', ['property_id' => ['properties']], ['uid', 'checkout_date']);
            $this->importSimple('cleaning_sessions', [
                'property_id' => ['properties'],
                'checkout_id' => ['property_checkouts'],
                'owner_id' => ['users'],
                'housekeeper_id' => ['users'],
            ], ['scheduled_date']);
            $this->importSimple('checklist_items', [
                'room_id' => ['rooms'],
                'session_id' => ['cleaning_sessions'],
                'task_id' => ['tasks'],
            ], []);
            $this->importChild('checklist_item_photos', 'checklist_items', 'checklist_item_id');
            $this->importSimple('checklist_reports', ['cleaning_session_id' => ['cleaning_sessions']], []);
            $this->importSettings();

            if ($dryRun) {
                DB::rollBack();
                $this->warn('Dry run — everything rolled back. No changes committed.');
            } else {
                DB::commit();
                $this->info('Import committed.');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Import failed, rolled back: ' . $e->getMessage());
            $this->error($e->getFile() . ':' . $e->getLine());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    // ---- schema-aware insert helper ------------------------------------

    protected function describeTable(string $table): array
    {
        if (!isset($this->describeCache[$table])) {
            $this->describeCache[$table] = DB::select("DESCRIBE `{$table}`");
        }
        return $this->describeCache[$table];
    }

    protected function fillRequiredDefaults(string $table, array $data, string $context = ''): array
    {
        foreach ($this->describeTable($table) as $col) {
            $field = $col->Field;
            if (array_key_exists($field, $data)) continue;
            if (str_contains($col->Extra, 'auto_increment')) continue;
            if ($col->Null === 'YES' || $col->Default !== null) continue;

            $fallback = match (true) {
                $field === 'uid' || str_ends_with($field, '_uid') => (string) Str::uuid(),
                str_contains($col->Type, 'tinyint(1)') => 0,
                str_starts_with($col->Type, 'int') || str_contains($col->Type, 'bigint') => 0,
                $col->Type === 'date' => now()->toDateString(),
                str_starts_with($col->Type, 'datetime') || str_starts_with($col->Type, 'timestamp') => now(),
                str_starts_with($col->Type, 'enum') => trim(explode(',', explode("'", $col->Type)[1] ?? "''")[0], "'"),
                default => '',
            };
            $data[$field] = $fallback;
            $this->warn("  [{$table}.{$field}] no value supplied ({$context}) — filled with fallback, review manually");
        }
        return $data;
    }

    // ---- infrastructure -----------------------------------------------

    protected function ensureMapTable(): void
    {
        if (!\Schema::hasTable('legacy_import_map')) {
            \Schema::create('legacy_import_map', function ($table) {
                $table->id();
                $table->string('source');
                $table->string('table_name');
                $table->string('old_id');
                $table->unsignedBigInteger('new_id');
                $table->timestamps();
                $table->unique(['source', 'table_name', 'old_id']);
            });
            $this->info('Created legacy_import_map tracking table.');
        }
    }

    protected function loadExistingMap(): void
    {
        foreach (DB::table('legacy_import_map')->get() as $row) {
            $this->idMap[$row->source][$row->table_name][$row->old_id] = $row->new_id;
        }
    }

    protected function remember(string $table, $oldId, $newId): void
    {
        $this->idMap['cleaning'][$table][$oldId] = $newId;
        DB::table('legacy_import_map')->updateOrInsert(
            ['source' => 'cleaning', 'table_name' => $table, 'old_id' => (string) $oldId],
            ['new_id' => $newId, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    protected function mapped(string $table, $oldId)
    {
        return $this->idMap['cleaning'][$table][$oldId] ?? null;
    }

    protected function sqliteRows(string $table): array
    {
        $stmt = $this->sqlite->query("SELECT * FROM {$table}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ---- importers ------------------------------------------------------

    protected function importUsers(): void
    {
        $count = 0;
        foreach ($this->sqliteRows('users') as $row) {
            if ($this->mapped('users', $row['id'])) continue;
            $existing = DB::table('users')->where('email', $row['email'])->first();
            if ($existing) {
                $this->remember('users', $row['id'], $existing->id);
                continue;
            }
            $data = $this->fillRequiredDefaults('users', [
                'name' => $row['name'] ?? $row['email'],
                'email' => $row['email'],
                'password' => $row['password'],
                'email_verified_at' => $row['email_verified_at'] ?? now(),
                'created_at' => $row['created_at'] ?? now(),
                'updated_at' => $row['updated_at'] ?? now(),
            ], 'users');
            $newId = DB::table('users')->insertGetId($data);
            $this->remember('users', $row['id'], $newId);
            $count++;
        }
        $this->info("users: {$count} inserted");
    }

    protected function importRolesAndPermissions(): void
    {
        foreach (['permissions', 'roles'] as $table) {
            $count = 0;
            foreach ($this->sqliteRows($table) as $row) {
                if ($this->mapped($table, $row['id'])) continue;
                $existing = DB::table($table)->where('name', $row['name'])->first();
                if ($existing) {
                    $this->remember($table, $row['id'], $existing->id);
                    continue;
                }
                $data = $this->fillRequiredDefaults($table, [
                    'name' => $row['name'],
                    'guard_name' => $row['guard_name'] ?? 'web',
                    'created_at' => $row['created_at'] ?? now(),
                    'updated_at' => $row['updated_at'] ?? now(),
                ], $table);
                $newId = DB::table($table)->insertGetId($data);
                $this->remember($table, $row['id'], $newId);
                $count++;
            }
            $this->info("{$table}: {$count} inserted");
        }

        $count = 0;
        foreach ($this->sqliteRows('role_has_permissions') as $row) {
            $roleId = $this->mapped('roles', $row['role_id']);
            $permId = $this->mapped('permissions', $row['permission_id']);
            if (!$roleId || !$permId) continue;
            if (!DB::table('role_has_permissions')->where('role_id', $roleId)->where('permission_id', $permId)->exists()) {
                DB::table('role_has_permissions')->insert(['role_id' => $roleId, 'permission_id' => $permId]);
                $count++;
            }
        }
        $this->info("role_has_permissions: {$count} inserted");

        $count = 0;
        foreach ($this->sqliteRows('model_has_roles') as $row) {
            if (($row['model_type'] ?? null) !== 'App\\Models\\User') continue;
            $roleId = $this->mapped('roles', $row['role_id']);
            $userId = $this->mapped('users', $row['model_id']);
            if (!$roleId || !$userId) continue;
            if (!DB::table('model_has_roles')->where('role_id', $roleId)->where('model_id', $userId)->where('model_type', 'App\\Models\\User')->exists()) {
                DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_id' => $userId, 'model_type' => 'App\\Models\\User']);
                $count++;
            }
        }
        $this->info("model_has_roles: {$count} inserted");
    }

    protected function importProperties(): void
    {
        $count = 0;
        foreach ($this->sqliteRows('properties') as $row) {
            if ($this->mapped('properties', $row['id'])) continue;
            $name = $row['name'] ?? ('Imported property ' . $row['id']);
            $data = $this->fillRequiredDefaults('properties', array_filter([
                'name' => $name,
                'slug' => Str::slug($name) . '-' . Str::random(4),
                'address' => $row['address'] ?? '',
                'city' => $row['city'] ?? '',
                'created_at' => $row['created_at'] ?? now(),
                'updated_at' => $row['updated_at'] ?? now(),
            ], fn ($v) => $v !== null), 'properties');
            $newId = DB::table('properties')->insertGetId($data);
            $this->remember('properties', $row['id'], $newId);
            $count++;
        }
        $this->info("properties: {$count} inserted — review for duplicates against root's existing properties");
    }

    protected function importSimple(string $table, array $fkMap, array $passthroughCols): void
    {
        $count = 0;
        foreach ($this->sqliteRows($table) as $row) {
            if (isset($row['id']) && $this->mapped($table, $row['id'])) continue;

            $data = [];
            $skip = false;
            foreach ($fkMap as $col => [$mapTable]) {
                if (!array_key_exists($col, $row) || $row[$col] === null) continue;
                $mappedId = $this->mapped($mapTable, $row[$col]);
                if (!$mappedId) { $skip = true; break; }
                $data[$col] = $mappedId;
            }
            if ($skip) continue;

            foreach ($passthroughCols as $col) {
                if (array_key_exists($col, $row)) $data[$col] = $row[$col];
            }
            $data['created_at'] = $row['created_at'] ?? now();
            $data['updated_at'] = $row['updated_at'] ?? now();

            $data = $this->fillRequiredDefaults($table, $data, $table);
            $newId = DB::table($table)->insertGetId($data);
            if (isset($row['id'])) $this->remember($table, $row['id'], $newId);
            $count++;
        }
        $this->info("{$table}: {$count} inserted");
    }

    protected function importChild(string $table, string $parentTable, string $fkCol, array $passthroughCols = ['path']): void
    {
        $count = 0;
        foreach ($this->sqliteRows($table) as $row) {
            $parentId = $this->mapped($parentTable, $row[$fkCol] ?? null);
            if (!$parentId) continue;
            $data = [$fkCol => $parentId, 'created_at' => $row['created_at'] ?? now(), 'updated_at' => $row['updated_at'] ?? now()];
            foreach ($passthroughCols as $col) {
                if (array_key_exists($col, $row) && $row[$col] !== null) $data[$col] = $row[$col];
            }
            $data = $this->fillRequiredDefaults($table, $data, $table);
            DB::table($table)->insert($data);
            $count++;
        }
        $this->info("{$table}: {$count} inserted");
    }

    protected function importPivot(string $table, string $leftTable, string $leftCol, string $rightTable, string $rightCol): void
    {
        $count = 0;
        foreach ($this->sqliteRows($table) as $row) {
            $leftId = $this->mapped($leftTable, $row[$leftCol] ?? null);
            $rightId = $this->mapped($rightTable, $row[$rightCol] ?? null);
            if (!$leftId || !$rightId) continue;
            if (!DB::table($table)->where($leftCol, $leftId)->where($rightCol, $rightId)->exists()) {
                DB::table($table)->insert([$leftCol => $leftId, $rightCol => $rightId]);
                $count++;
            }
        }
        $this->info("{$table}: {$count} inserted");
    }

    protected function importSettings(): void
    {
        $count = 0;
        foreach ($this->sqliteRows('settings') as $row) {
            $key = $row['key'] ?? null;
            if (!$key) continue;
            if (!DB::table('settings')->where('key', $key)->exists()) {
                $data = $this->fillRequiredDefaults('settings', [
                    'key' => $key,
                    'value' => $row['value'] ?? null,
                    'created_at' => $row['created_at'] ?? now(),
                    'updated_at' => $row['updated_at'] ?? now(),
                ], 'settings');
                DB::table('settings')->insert($data);
                $count++;
            }
        }
        $this->info("settings: {$count} inserted (existing keys skipped)");
    }
}
