<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * What happens to users who currently hold a removed role.
     * 'staff'  -> 'manager'  (staff could already update guests; manager is the closest remaining role)
     * 'viewer' -> null       (role removed, account kept but has no access until an admin assigns one;
     *                         we do NOT promote read-only users to a write role)
     * Edit this map before running if you want something different.
     */
    private array $map = ['staff' => 'manager', 'viewer' => null];

    public function up(): void
    {
        $guard = config('auth.defaults.guard', 'web');

        foreach ($this->map as $old => $target) {
            $oldRole = Role::where('name', $old)->where('guard_name', $guard)->first();
            if (! $oldRole) {
                continue;
            }

            $userIds = DB::table('model_has_roles')
                ->where('role_id', $oldRole->id)
                ->where('model_type', 'App\\Models\\User')
                ->pluck('model_id');

            if ($target !== null) {
                $newRole = Role::where('name', $target)->where('guard_name', $guard)->first();
                if (! $newRole) {
                    throw new RuntimeException("Target role '{$target}' does not exist; cannot migrate '{$old}' users.");
                }
                foreach ($userIds as $id) {
                    DB::table('model_has_roles')->insertOrIgnore([
                        'role_id' => $newRole->id, 'model_type' => 'App\\Models\\User', 'model_id' => $id,
                    ]);
                }
            }

            if ($userIds->isNotEmpty()) {
                logger()->warning("Role '{$old}' removed; users " . ($target ? "moved to '{$target}'" : 'left with no role') . ': ' . $userIds->implode(','));
            }

            DB::table('model_has_roles')->where('role_id', $oldRole->id)->delete();
            DB::table('role_has_permissions')->where('role_id', $oldRole->id)->delete();
            $oldRole->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (array_keys($this->map) as $name) {
            Role::findOrCreate($name, config('auth.defaults.guard', 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
