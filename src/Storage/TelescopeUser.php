<?php

namespace Laravel\Telescope\Storage;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

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
        ]);
    }

    /**
     * Hash passwords when they are assigned.
     */
    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = Hash::needsRehash($value) ? Hash::make($value) : $value;
    }
}
