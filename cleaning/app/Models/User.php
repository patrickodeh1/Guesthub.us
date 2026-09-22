<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Setting;


class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone_number',
        'profile_photo_path',
        'owner_id',
        'preferences',
        'is_active',
        'terminated_at',
        'terminated_by',
        'termination_reason',
        'must_change_password',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function terminator()
    {
        return $this->belongsTo(User::class, 'terminated_by');
    }

    // A housekeeper belongs to many owners/companies
    public function managedOwners()
    {
        return $this->belongsToMany(User::class, 'housekeeper_owner', 'housekeeper_id', 'owner_id');
    }

    // An owner/company manages many housekeepers
    public function managedHousekeepers()
    {
        return $this->belongsToMany(User::class, 'housekeeper_owner', 'owner_id', 'housekeeper_id');
    }

    // Properties assigned to this user
    public function properties()
    {
        return $this->belongsToMany(\App\Models\Property::class, 'property_user');
    }

    /**
     * Scope a query to only include active users.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include inactive/terminated users.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope a query to only include housekeepers.
     */
    public function scopeHousekeeper($query)
    {
        return $query->role('housekeeper');
    }

    /**
     * Check if user is active.
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Check if user is terminated.
     */
    public function isTerminated(): bool
    {
        return ! $this->is_active;
    }

    /**
     * Deactivate / terminate cleaner.
     */
    public function deactivate(User $actor, string $reason): bool
    {
        return $this->update([
            'is_active' => false,
            'terminated_at' => now(),
            'terminated_by' => $actor->id,
            'termination_reason' => $reason,
        ]);
    }

    /**
     * Reactivate cleaner account.
     */
    public function reactivate(): bool
    {
        return $this->update([
            'is_active' => true,
            'terminated_at' => null,
            'terminated_by' => null,
            'termination_reason' => null,
        ]);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'must_change_password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'preferences' => 'array',
            'is_active' => 'boolean',
            'terminated_at' => 'datetime',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Get the URL to the user's profile photo.
     */
    public function getProfilePhotoUrlAttribute(): string
    {
        if ($this->profile_photo_path) {
            $path = str_replace('\\', '/', trim((string) $this->profile_photo_path));
            $path = ltrim($path, '/');
            if (str_starts_with($path, 'storage/')) {
                $path = substr($path, strlen('storage/'));
            }

            return str_starts_with($this->profile_photo_path, 'http')
                ? $this->profile_photo_path
                : url('file/' . $path);
        }

        // Get theme color from settings and remove '#' for API
        try {
            $themeColor = Setting::get('theme_color', '#842eb8');
        } catch (\Exception $e) {
            $themeColor = '#842eb8';
        }
        $backgroundColor = str_replace('#', '', $themeColor);

        // Return a default avatar placeholder
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=' . $backgroundColor . '&color=fff&size=128';
    }
}
