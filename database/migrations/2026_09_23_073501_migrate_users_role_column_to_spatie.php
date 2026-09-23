<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const ROLE_MAP = [
        'owner' => 'admin',
        'manager' => 'manager',
        'staff' => 'staff',
        'viewer' => 'viewer',
    ];

    public function up(): void
    {
        foreach (self::ROLE_MAP as $legacyRole => $spatieRole) {
            User::query()
                ->where('role', $legacyRole)
                ->eachById(function (User $user) use ($spatieRole): void {
                    $user->assignRole($spatieRole);
                });
        }
    }

    public function down(): void
    {
        // Best effort only: users with multiple Spatie roles are restored by precedence.
        foreach (array_reverse(self::ROLE_MAP, true) as $legacyRole => $spatieRole) {
            User::query()
                ->role($spatieRole)
                ->eachById(function (User $user) use ($legacyRole): void {
                    $user->update(['role' => $legacyRole]);
                });
        }
    }
};
