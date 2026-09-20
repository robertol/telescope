<?php

namespace Laravel\Telescope\Observability\Storage;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ObservabilityEntryQuery
{
    public function __construct(protected string $connection)
    {
    }

    /**
     * @param  array<int|string, mixed>  $filters
     * @param  array{created_at: mixed, id: mixed}|null  $cursor
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function latest(string $table, array $filters = [], ?array $cursor = null, int $limit = 50): Collection
    {
        $query = $this->table($table);

        foreach ($filters as $key => $value) {
            if (is_int($key) && is_array($value)) {
                $query->where($value[0], $value[1], $value[2] ?? true);

                continue;
            }

            $query->where($key, $value);
        }

        if ($cursor !== null) {
            $query->where(function ($query) use ($cursor) {
                $query->where('created_at', '<', $cursor['created_at'])
                    ->orWhere(function ($query) use ($cursor) {
                        $query->where('created_at', $cursor['created_at'])
                            ->where('id', '<', $cursor['id']);
                    });
            });
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    protected function table(string $table)
    {
        return DB::connection($this->connection)->table($table);
    }
}
