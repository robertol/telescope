<?php

namespace Laravel\Telescope\Storage;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Telescope\Database\Factories\EntryModelFactory;

class EntryModel extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'telescope_entries';

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    const UPDATED_AT = null;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'content' => 'json',
    ];

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'uuid';

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Prevent Eloquent from overriding uuid with `lastInsertId`.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Scope the query for the given query options.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $type
     * @param  \Laravel\Telescope\Storage\EntryQueryOptions  $options
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithTelescopeOptions($query, $type, EntryQueryOptions $options)
    {
        $this->whereType($query, $type)
                ->whereBatchId($query, $options)
                ->whereTag($query, $options)
                ->whereEndpoint($query, $options)
                ->whereMinDuration($query, $options)
                ->whereIp($query, $options)
                ->whereFamilyHash($query, $options)
                ->whereBeforeSequence($query, $options)
                ->filter($query, $options);

        return $query;
    }

    /**
     * Scope the query for the given type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $type
     * @return $this
     */
    protected function whereType($query, $type)
    {
        $query->when($type, function ($query, $type) {
            return $query->where('type', $type);
        });

        return $this;
    }

    /**
     * Scope the query for the given batch ID.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Laravel\Telescope\Storage\EntryQueryOptions  $options
     * @return $this
     */
    protected function whereBatchId($query, EntryQueryOptions $options)
    {
        $query->when($options->batchId, function ($query, $batchId) {
            return $query->where('batch_id', $batchId);
        });

        return $this;
    }

    /**
     * Scope the query for the given type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Laravel\Telescope\Storage\EntryQueryOptions  $options
     * @return $this
     */
    protected function whereTag($query, EntryQueryOptions $options)
    {
        $query->when($options->tag, function ($query, $tag) {
            $tags = collect(explode(',', $tag))->map(fn ($tag) => trim($tag));

            if ($tags->isEmpty()) {
                return $query;
            }

            return $query->whereIn('uuid', function ($query) use ($tags) {
                $query->select('entry_uuid')->from('telescope_entries_tags')
                    ->whereIn('entry_uuid', function ($query) use ($tags) {
                        $query->select('entry_uuid')->from('telescope_entries_tags')->whereIn('tag', $tags->all());
                    });
            });
        });

        return $this;
    }

    /**
     * Scope the query for the given endpoint pattern.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Laravel\Telescope\Storage\EntryQueryOptions  $options
     * @return $this
     */
    protected function whereEndpoint($query, EntryQueryOptions $options)
    {
        $query->when($options->endpoint, function ($query, $endpoint) {
            $patterns = collect(explode(',', $endpoint))->map(fn ($pattern) => trim($pattern))->filter();

            if ($patterns->isEmpty()) {
                return $query;
            }

            return $query->where(function ($query) use ($patterns) {
                foreach ($patterns as $pattern) {
                    $query->orWhere(function ($query) use ($pattern) {
                        $method = null;
                        $path = $pattern;

                        if (str_contains($pattern, ':')) {
                            [$method, $path] = explode(':', $pattern, 2);
                            $method = strtoupper(trim($method));
                            $path = trim($path);
                        }

                        if ($method && $method !== '*') {
                            $this->whereContentAttribute($query, 'method', '=', $method);
                        }

                        $like = '%'.str_replace('*', '%', ltrim($path, '/')).'%';
                        $this->whereContentAttribute($query, 'uri', 'like', $like);
                    });
                }
            });
        });

        return $this;
    }

    /**
     * Constrain a JSON content attribute in a driver-safe way.
     *
     * Telescope stores `content` as longText; PostgreSQL rejects `text ->>` operators
     * used by Eloquent's `content->key` syntax unless the column is cast to json/jsonb.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $attribute
     * @param  string  $operator
     * @param  string  $value
     * @return void
     */
    protected function whereContentAttribute($query, string $attribute, string $operator, string $value): void
    {
        if (! in_array($attribute, ['method', 'uri', 'ip_address'], true)) {
            throw new \InvalidArgumentException("Unsupported Telescope content attribute [{$attribute}].");
        }

        if ($query->getConnection()->getDriverName() === 'pgsql') {
            $query->whereRaw("(content::jsonb)->>'{$attribute}' {$operator} ?", [$value]);

            return;
        }

        $query->where("content->{$attribute}", $operator, $value);
    }

    /**
     * Scope the query to entries slower than the given duration in milliseconds.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Laravel\Telescope\Storage\EntryQueryOptions  $options
     * @return $this
     */
    protected function whereMinDuration($query, EntryQueryOptions $options)
    {
        $query->when($options->minDuration, function ($query, $minDuration) {
            if ($query->getConnection()->getDriverName() === 'pgsql') {
                return $query->whereRaw("((content::jsonb)->>'duration')::numeric > ?", [$minDuration]);
            }

            return $query->whereRaw("cast(json_extract(content, '$.duration') as real) > ?", [$minDuration]);
        });

        return $this;
    }

    /**
     * Scope the query to request entries from the given client IP.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Laravel\Telescope\Storage\EntryQueryOptions  $options
     * @return $this
     */
    protected function whereIp($query, EntryQueryOptions $options)
    {
        $query->when($options->ip, function ($query, $ip) {
            $this->whereContentAttribute($query, 'ip_address', '=', $ip);
        });

        return $this;
    }

    /**
     * Scope the query for the given type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Laravel\Telescope\Storage\EntryQueryOptions  $options
     * @return $this
     */
    protected function whereFamilyHash($query, EntryQueryOptions $options)
    {
        $query->when($options->familyHash, function ($query, $hash) {
            return $query->where('family_hash', $hash);
        });

        return $this;
    }

    /**
     * Scope the query for the given pagination options.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Laravel\Telescope\Storage\EntryQueryOptions  $options
     * @return $this
     */
    protected function whereBeforeSequence($query, EntryQueryOptions $options)
    {
        $query->when($options->beforeSequence, function ($query, $beforeSequence) {
            return $query->where('sequence', '<', $beforeSequence);
        });

        return $this;
    }

    /**
     * Scope the query for the given display options.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Laravel\Telescope\Storage\EntryQueryOptions  $options
     * @return $this
     */
    protected function filter($query, EntryQueryOptions $options)
    {
        if ($options->familyHash || $options->tag || $options->batchId || $options->endpoint || $options->minDuration || $options->ip) {
            return $this;
        }

        $query->where('should_display_on_index', true);

        return $this;
    }

    /**
     * Get the current connection name for the model.
     *
     * @return string
     */
    public function getConnectionName()
    {
        return config('telescope.storage.database.connection');
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public static function newFactory()
    {
        return EntryModelFactory::new();
    }
}
