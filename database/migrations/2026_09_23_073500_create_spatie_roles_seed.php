<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['admin', 'company', 'owner', 'manager', 'staff', 'viewer', 'housekeeper'] as $name) {
            Role::firstOrCreate(
                ['name' => $name],
                ['guard_name' => config('auth.defaults.guard', 'web')],
            );
        }
    }

    public function down(): void
    {
        Role::whereIn('name', ['admin', 'company', 'owner', 'manager', 'staff', 'viewer', 'housekeeper'])
            ->where('guard_name', config('auth.defaults.guard', 'web'))
            ->delete();
    }
};
