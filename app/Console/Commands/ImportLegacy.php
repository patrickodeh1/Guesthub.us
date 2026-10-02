<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

class ImportLegacy extends Command
{
    protected $signature = 'import:legacy {files* : Backup files (MySQL dump or SQLite)} {--map= : Flipstatus:Guesthub property id pairs, e.g. 1:1,2:2} {--dry-run : Run everything, then roll back} {--inspect : Only print per-table counts and columns the merged schema lacks}';
    protected $description = 'Import the Guesthub and Flipstatus backup files into the merged database';

    private const GUESTHUB_TABLES = [
        'users', 'settings', 'categories', 'category_pages', 'properties', 'property_category', 'amenities',
        'media_folders', 'media_files', 'instruction_steps', 'instruction_step_images', 'bookings', 'guest_notices',
        'guest_sessions', 'sms_consent_events', 'charges', 'activity_logs', 'property_locks',
        'property_availabilities', 'contact_messages', 'privacy_requests', 'early_access_leads',
    ];

    private const ROLE_MAP = ['owner' => 'admin', 'manager' => 'manager', 'staff' => 'staff', 'viewer' => 'viewer'];

    public function handle(): int
    {
        $sources = [];
        foreach ($this->argument('files') as $file) {
            if (! is_file($file)) { $this->error("File not found: $file"); return 1; }
            $src = file_get_contents($file, false, null, 0, 15) === 'SQLite format 3' ? new LegacySqliteSource($file) : new LegacyDumpSource($file);
            $kind = ($src->has('bookings') && $src->has('category_pages')) ? 'guesthub' : ($src->has('cleaning_sessions') ? 'flipstatus' : 'unknown');
            if ($kind === 'unknown' || isset($sources[$kind])) { $this->error("Cannot classify $file as a single Guesthub or Flipstatus backup (got: $kind)."); return 1; }
            $sources[$kind] = $src;
            $this->info(basename($file).' => '.$kind.' ('.($src instanceof LegacySqliteSource ? 'sqlite' : 'mysql dump').')');
        }

        if ($this->option('inspect')) { foreach ($sources as $kind => $src) { $this->inspect($kind, $src); } return 0; }

        DB::beginTransaction();
        try {
            if (isset($sources['guesthub'])) { $this->guesthubStage($sources['guesthub']); }
            if (isset($sources['flipstatus'])) { $this->flipstatusStage($sources['flipstatus']); }
            if ($this->option('dry-run')) { DB::rollBack(); $this->warn('DRY RUN: everything rolled back.'); }
            else { DB::commit(); app(PermissionRegistrar::class)->forgetCachedPermissions(); $this->info('Committed.'); }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('FAILED, rolled back: '.$e->getMessage());
            return 1;
        }
        return 0;
    }

    private function flipstatusStage($src): void
    {
        $this->line('--- Flipstatus stage');
        if (DB::table('settings')->where('key', 'legacy_flipstatus_imported_at')->exists()) {
            throw new \RuntimeException('Flipstatus data was already imported into this database.');
        }
        $m = ['user' => [], 'prop' => [], 'room' => [], 'task' => [], 'video' => [], 'checkout' => [], 'session' => [], 'item' => []];
        $roleIds = DB::table('roles')->pluck('id', 'name');

        $names = collect(iterator_to_array($src->rows('roles'), false))->pluck('name', 'id');
        $flipRole = [];
        foreach ($src->rows('model_has_roles') as $r) {
            if (str_contains($r['model_type'], 'User')) { $flipRole[$r['model_id']] = $names[$r['role_id']] ?? null; }
        }
        $ucols = array_values(array_diff(array_intersect($src->columns('users'), Schema::getColumnListing('users')), ['id', 'owner_id', 'created_by', 'terminated_by']));
        $matched = $created = 0; $defer = [];
        foreach ($src->rows('users') as $u) {
            $existing = DB::table('users')->whereRaw('lower(email) = ?', [strtolower((string) $u['email'])])->value('id');
            if ($existing) { $m['user'][$u['id']] = $existing; $matched++; continue; }
            $new = DB::table('users')->insertGetId(array_intersect_key($u, array_flip($ucols)));
            $m['user'][$u['id']] = $new; $defer[$u['id']] = $u; $created++;
            $rn = $flipRole[$u['id']] ?? null;
            if ($rn && isset($roleIds[$rn])) {
                DB::table('model_has_roles')->insertOrIgnore(['role_id' => $roleIds[$rn], 'model_type' => 'App\\Models\\User', 'model_id' => $new]);
            }
        }
        foreach ($defer as $oid => $u) {
            $upd = [];
            foreach (['owner_id', 'created_by', 'terminated_by'] as $c) {
                if (! empty($u[$c]) && isset($m['user'][$u[$c]]) && Schema::hasColumn('users', $c)) { $upd[$c] = $m['user'][$u[$c]]; }
            }
            if ($upd) { DB::table('users')->where('id', $m['user'][$oid])->update($upd); }
        }
        $this->line("  users: $matched matched by email (Guesthub row and role kept), $created created");

        $norm = fn ($s) => preg_replace('/[^a-z]/', '', strtolower((string) $s));
        $gh = DB::table('properties')->get(['id', 'name']);
        $over = [];
        if ($this->option('map')) { foreach (explode(',', $this->option('map')) as $pair) { [$f, $g] = explode(':', $pair); $over[(int) $f] = (int) $g; } }
        $fill = ['photo_path', 'beds', 'baths', 'geo_radius_m', 'ical_url', 'vrbo_ical_url'];
        $always = ['notify_cleaning_started', 'notify_cleaning_finished', 'notify_photo_started', 'notify_task_notes'];
        foreach ($src->rows('properties') as $p) {
            $fid = $p['id'];
            if (isset($over[$fid])) { $gid = $over[$fid]; }
            else {
                $n = $norm($p['name']);
                $c = $gh->filter(function ($g) use ($norm, $n) { $x = $norm($g->name); return $x !== '' && $n !== '' && ($x === $n || str_starts_with($x, $n) || str_starts_with($n, $x)); })->pluck('id');
                if ($c->count() !== 1) { throw new \RuntimeException("Flipstatus property #$fid '{$p['name']}' matched {$c->count()} Guesthub properties. Pass --map=$fid:<guesthub id>"); }
                $gid = $c->first();
            }
            if (in_array($gid, $m['prop'], true)) { throw new \RuntimeException("Two Flipstatus properties map to Guesthub property #$gid. Use --map."); }
            $m['prop'][$fid] = $gid;
            $cur = (array) DB::table('properties')->where('id', $gid)->first();
            $upd = [];
            foreach ($fill as $c) { if (($p[$c] ?? null) !== null && ($p[$c] ?? '') !== '' && ($cur[$c] ?? null) === null || ($cur[$c] ?? null) === '') { if (($p[$c] ?? '') !== '' && ($p[$c] ?? null) !== null) { $upd[$c] = $p[$c]; } } }
            foreach ($always as $c) { if (array_key_exists($c, $p)) { $upd[$c] = $p[$c]; } }
            if (! empty($p['owner_id']) && isset($m['user'][$p['owner_id']])) { $upd['owner_id'] = $m['user'][$p['owner_id']]; }
            if ($upd) { DB::table('properties')->where('id', $gid)->update($upd); }
            $this->line("  property: Flipstatus #$fid '{$p['name']}' -> Guesthub #$gid '".$gh->firstWhere('id', $gid)->name."' (".count($upd).' fields filled)');
        }

        $sporadic = fn ($r) => $this->remapJson($r, $m, true);
        $this->copyRemapped($src, 'rooms', [], $m, 'room');
        $this->copyRemapped($src, 'tasks', [], $m, 'task');
        $this->copyRemapped($src, 'instructional_videos', ['created_by' => 'user'], $m, 'video');
        $this->copyRemapped($src, 'property_room', ['property_id' => 'prop', 'room_id' => 'room'], $m);
        $this->copyRemapped($src, 'room_task', ['room_id' => 'room', 'task_id' => 'task'], $m);
        $this->copyRemapped($src, 'property_tasks', ['property_id' => 'prop', 'task_id' => 'task'], $m);
        $this->copyRemapped($src, 'task_media', ['task_id' => 'task'], $m);
        $this->copyRemapped($src, 'instructional_video_task', ['task_id' => 'task', 'instructional_video_id' => 'video'], $m);
        $this->copyRemapped($src, 'property_instructional_videos', ['property_id' => 'prop', 'instructional_video_id' => 'video'], $m);
        $this->copyRemapped($src, 'cleaner_instruction_familiarities', ['user_id' => 'user', 'task_id' => 'task', 'instructional_video_id' => 'video'], $m);
        $this->copyRemapped($src, 'property_user', ['property_id' => 'prop', 'user_id' => 'user'], $m);
        $this->copyRemapped($src, 'housekeeper_owner', ['housekeeper_id' => 'user', 'owner_id' => 'user'], $m);
        $this->copyRemapped($src, 'property_checkouts', ['property_id' => 'prop'], $m, 'checkout');
        $this->copyRemapped($src, 'cleaning_sessions', ['property_id' => 'prop', 'owner_id' => 'user', 'housekeeper_id' => 'user', 'gps_override_approved_by' => 'user', 'checkout_id' => 'checkout'], $m, 'session', function ($d) use (&$m) {
            if (isset($d['sporadic_tasks'])) { $d['sporadic_tasks'] = $this->remapJson($d['sporadic_tasks'], $m, true); }
            if (isset($d['skipped_rooms'])) { $d['skipped_rooms'] = $this->remapJson($d['skipped_rooms'], $m, false); }
            return $d;
        });
        $this->copyRemapped($src, 'checklist_items', ['session_id' => 'session', 'room_id' => 'room', 'task_id' => 'task', 'user_id' => 'user'], $m, 'item');
        $this->copyRemapped($src, 'checklist_reports', ['session_id' => 'session', 'reported_by' => 'user', 'resolved_by' => 'user'], $m);
        $this->copyRemapped($src, 'room_photos', ['session_id' => 'session', 'room_id' => 'room'], $m);
        $this->copyRemapped($src, 'checklist_item_photos', ['checklist_item_id' => 'item', 'room_id' => 'room', 'property_id' => 'prop'], $m);
        $this->copyRemapped($src, 'notification_logs', ['property_id' => 'prop', 'user_id' => 'user', 'cleaning_session_id' => 'session'], $m);
        $this->copyRemapped($src, 'property_notification_recipients', ['property_id' => 'prop'], $m);
        $this->copyRemapped($src, 'training_completions', ['user_id' => 'user', 'property_id' => 'prop', 'cleaning_session_id' => 'session', 'task_id' => 'task', 'instructional_video_id' => 'video'], $m);
        $this->copyRemapped($src, 'assignment_training_snapshots', ['cleaning_session_id' => 'session', 'task_id' => 'task', 'instructional_video_id' => 'video'], $m);
        $this->copyRemapped($src, 'resource_completions', ['user_id' => 'user', 'property_id' => 'prop'], $m);
        $this->copyRemapped($src, 'photo_blobs', [], $m);

        $added = 0;
        foreach ($src->rows('settings') as $s) {
            if (! DB::table('settings')->where('key', $s['key'])->exists()) { DB::table('settings')->insert(['key' => $s['key'], 'value' => $s['value'], 'created_at' => now(), 'updated_at' => now()]); $added++; }
        }
        $this->line("  settings: $added Flipstatus keys added (existing keys untouched)");
        DB::table('settings')->insert(['key' => 'legacy_flipstatus_imported_at', 'value' => now()->toDateTimeString(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function copyRemapped($src, string $t, array $fks, array &$m, ?string $own = null, ?callable $tweak = null): void
    {
        if (! $src->has($t) || ! Schema::hasTable($t)) { $this->line("  $t: skipped (missing on one side)"); return; }
        $cols = array_values(array_diff(array_intersect($src->columns($t), Schema::getColumnListing($t)), ['id']));
        $parsed = $in = $nulled = 0;
        foreach ($src->rows($t) as $row) {
            $parsed++;
            foreach ($fks as $c => $map) {
                if (! array_key_exists($c, $row) || $row[$c] === null) { continue; }
                if (isset($m[$map][$row[$c]])) { $row[$c] = $m[$map][$row[$c]]; } else { $row[$c] = null; $nulled++; }
            }
            $data = array_intersect_key($row, array_flip($cols));
            if ($tweak) { $data = $tweak($data); }
            if ($t === 'instructional_videos') { $ulid = (string) \Illuminate\Support\Str::ulid(); DB::table($t)->insert($data + ['id' => $ulid]); $m[$own][$row['id']] = $ulid; $in++; }
            elseif ($own) { $m[$own][$row['id']] = DB::table($t)->insertGetId($data); $in++; }
            else { $in += DB::table($t)->insertOrIgnore($data); }
        }
        $line = "  $t: parsed $parsed, inserted $in".($nulled ? ", $nulled links pointed at missing rows and were set to NULL" : '');
        ($in < $parsed || $nulled) ? $this->warn($line) : $this->line($line);
    }

    private function remapJson($json, array $m, bool $compound)
    {
        if ($json === null || $json === '') { return $json; }
        $a = json_decode($json, true);
        if (! is_array($a)) { return $json; }
        $out = array_map(function ($v) use ($m, $compound) {
            if (is_int($v) || (is_string($v) && ctype_digit($v))) { return $m[$compound ? 'task' : 'room'][(int) $v] ?? $v; }
            if ($compound && is_string($v) && preg_match('/^(\d+)_(\d+)$/', $v, $x)) { return ($m['task'][(int) $x[1]] ?? $x[1]).'_'.($m['room'][(int) $x[2]] ?? $x[2]); }
            return $v;
        }, $a);
        return json_encode($out);
    }

    private function inspect(string $kind, $src): void
    {
        $rows = [];
        foreach ($src->tables() as $t) {
            $n = 0; foreach ($src->rows($t) as $_) { $n++; }
            $missing = Schema::hasTable($t) ? implode(', ', array_diff($src->columns($t), Schema::getColumnListing($t))) : 'NO TARGET TABLE';
            $rows[] = [$t, $n, $missing];
        }
        $this->line(strtoupper($kind));
        $this->table(['table', 'rows', 'source columns missing in merged schema'], $rows);
    }

    private function guesthubStage($src): void
    {
        $this->line('--- Guesthub stage');
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $legacyRoles = [];
        foreach (self::GUESTHUB_TABLES as $t) {
            if (! $src->has($t)) { $this->line("  $t: not in backup"); continue; }
            if (! Schema::hasTable($t)) { $this->warn("  $t: no target table, SKIPPED"); continue; }
            $common = array_values(array_intersect($src->columns($t), Schema::getColumnListing($t)));
            $dropped = array_diff($src->columns($t), $common);
            $before = DB::table($t)->count(); $parsed = 0; $buf = [];
            foreach ($src->rows($t) as $row) {
                $parsed++;
                if ($t === 'settings') {
                    DB::table('settings')->updateOrInsert(['key' => $row['key']], ['value' => $row['value'], 'updated_at' => now()]);
                    continue;
                }
                if (in_array($t, ['categories', 'guest_notices'], true)) {
                    $r = array_intersect_key($row, array_flip($common));
                    DB::table($t)->upsert([$r], ['id'], array_values(array_diff($common, ['id'])));
                    continue;
                }
                if ($t === 'users') { $legacyRoles[$row['id']] = $row['role'] ?? null; }
                $buf[] = array_intersect_key($row, array_flip($common));
                if (count($buf) >= 200) { DB::table($t)->insertOrIgnore($buf); $buf = []; }
            }
            if ($buf) { DB::table($t)->insertOrIgnore($buf); }
            $after = DB::table($t)->count();
            $line = "  $t: parsed $parsed, table $before -> $after".($dropped ? ' | dropped columns: '.implode(',', $dropped) : '');
            (! in_array($t, ['settings', 'categories', 'guest_notices'], true) && $after - $before < $parsed) ? $this->warn($line.'  <-- fewer rows than parsed') : $this->line($line);
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $roleIds = DB::table('roles')->where('guard_name', config('auth.defaults.guard', 'web'))->pluck('id', 'name');
        $assigned = 0; $none = 0;
        foreach ($legacyRoles as $uid => $legacy) {
            $name = self::ROLE_MAP[$legacy] ?? null;
            if (! $name || ! isset($roleIds[$name])) { $none++; continue; }
            DB::table('model_has_roles')->insertOrIgnore(['role_id' => $roleIds[$name], 'model_type' => 'App\\Models\\User', 'model_id' => $uid]);
            $assigned++;
        }
        $this->line("  roles: $assigned users assigned a Spatie role, $none with no usable legacy role");
    }
}

final class LegacyDumpSource
{
    private const ESC = ['n' => "\n", 'r' => "\r", 't' => "\t", '0' => "\0", 'Z' => "\x1a", 'b' => "\x08"];
    private array $schema = [];

    public function __construct(private string $path)
    {
        $h = fopen($path, 'r'); $cur = null;
        while (($line = fgets($h)) !== false) {
            if ($cur === null) {
                if (preg_match('/^CREATE TABLE `([^`]+)` \(/', $line, $m)) { $cur = $m[1]; $this->schema[$cur] = []; }
                continue;
            }
            if (preg_match('/^\s+`([^`]+)`\s/', $line, $m)) { $this->schema[$cur][] = $m[1]; continue; }
            if ($line[0] === ')') { $cur = null; }
        }
        fclose($h);
    }

    public function tables(): array { return array_keys($this->schema); }
    public function has(string $t): bool { return isset($this->schema[$t]); }
    public function columns(string $t): array { return $this->schema[$t]; }

    public function rows(string $t): \Generator
    {
        $prefix = "INSERT INTO `$t` "; $len = strlen($prefix);
        $h = fopen($this->path, 'r');
        while (($line = fgets($h)) !== false) {
            if (strncmp($line, $prefix, $len) !== 0) { continue; }
            $v = strpos($line, ' VALUES ');
            $cols = $this->schema[$t];
            if (preg_match('/^\((.*)\)$/', substr($line, $len, $v - $len), $m)) {
                $cols = array_map(fn ($c) => trim($c, '` '), explode(',', $m[1]));
            }
            yield from $this->tuples($line, $v + 8, $cols);
        }
        fclose($h);
    }

    private function tuples(string $s, int $i, array $cols): \Generator
    {
        $n = strlen($s); $want = count($cols);
        while (true) {
            while ($i < $n && $s[$i] !== '(' && $s[$i] !== ';') { $i++; }
            if ($i >= $n) { throw new \RuntimeException('INSERT statement did not end with ;'); }
            if ($s[$i] === ';') { return; }
            $i++; $row = [];
            while (true) {
                if ($s[$i] === "'") {
                    $i++; $buf = '';
                    while (true) {
                        $j = $i + strcspn($s, "\\'", $i);
                        $buf .= substr($s, $i, $j - $i);
                        if ($s[$j] === '\\') { $buf .= self::ESC[$s[$j + 1]] ?? $s[$j + 1]; $i = $j + 2; }
                        elseif (($s[$j + 1] ?? '') === "'") { $buf .= "'"; $i = $j + 2; }
                        else { $i = $j + 1; break; }
                    }
                    $row[] = $buf;
                } else {
                    $j = $i + strcspn($s, ',)', $i);
                    $tok = substr($s, $i, $j - $i); $i = $j;
                    $row[] = $tok === 'NULL' ? null : (str_starts_with($tok, '0x') ? hex2bin(substr($tok, 2)) : $tok);
                }
                if ($s[$i++] === ')') { break; }
            }
            if (count($row) !== $want) { throw new \RuntimeException('Row has '.count($row)." values, expected $want"); }
            yield array_combine($cols, $row);
            if (($s[$i] ?? '') === ',') { $i++; }
        }
    }
}

final class LegacySqliteSource
{
    private \PDO $pdo;

    public function __construct(string $path)
    {
        $this->pdo = new \PDO('sqlite:'.$path);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function tables(): array
    {
        return $this->pdo->query("select name from sqlite_master where type='table' and name not like 'sqlite_%' order by name")->fetchAll(\PDO::FETCH_COLUMN);
    }
    public function has(string $t): bool { return in_array($t, $this->tables(), true); }
    public function columns(string $t): array { return array_column($this->pdo->query("pragma table_info(\"$t\")")->fetchAll(\PDO::FETCH_ASSOC), 'name'); }

    public function rows(string $t): \Generator
    {
        foreach ($this->pdo->query("select * from \"$t\"", \PDO::FETCH_ASSOC) as $row) { yield $row; }
    }
}
