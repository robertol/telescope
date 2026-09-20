<?php

namespace Laravel\Telescope\Storage;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Telescope\Telescope;

class TelescopeUser extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'telescope_users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'must_change_password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'must_change_password' => 'boolean',
    ];

    /**
     * Get the current connection name for the model.
     */
    public function getConnectionName(): ?string
    {
        return config('telescope.storage.database.connection');
    }

    /**
     * Create the default dashboard user when the table is empty.
     */
    public static function ensureDefault(): void
    {
        if (! Schema::connection(config('telescope.storage.database.connection'))->hasTable('telescope_users')) {
            return;
        }

        if (static::query()->exists()) {
            return;
        }

        static::query()->create([
            'name' => (string) config('telescope.auth.default.name', 'Telescope'),
            'email' => (string) config('telescope.auth.default.email', 'telescope@local'),
            'password' => (string) config('telescope.auth.default.password', 'telescope'),
            'must_change_password' => true,
        ]);
    }

    /**
     * Whether the given request still needs a first-access password change.
     */
    public static function requiresPasswordChange($request): bool
    {
        $user = Telescope::dashboardUser($request);

        if ($user) {
            return (bool) $user->must_change_password;
        }

        if ($request->user()) {
            return false;
        }

        return static::hasPendingPasswordChange();
    }

    /**
     * Whether any dashboard user still needs to change the initial password.
     */
    public static function hasPendingPasswordChange(): bool
    {
        $connection = config('telescope.storage.database.connection');

        if (! Schema::connection($connection)->hasTable('telescope_users')) {
            return false;
        }

        if (! Schema::connection($connection)->hasColumn('telescope_users', 'must_change_password')) {
            return false;
        }

        return static::query()->where('must_change_password', true)->exists();
    }

    /**
     * Hash passwords when they are assigned.
     */
    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = Hash::needsRehash($value) ? Hash::make($value) : $value;
    }
}
