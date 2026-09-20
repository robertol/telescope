<?php

namespace Laravel\Telescope\Observability\Storage;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class ObservabilityPruner
{
    public function __construct(protected string $connection)
    {
    }

    public function pruneTable(string $table, DateTimeInterface $before, int $chunk): int
    {
        $total = 0;

        do {
            $ids = $this->table($table)
                ->where('created_at', '<', $before)
                ->orderBy('id')
                ->limit($chunk)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted = $this->table($table)->whereIn('id', $ids)->delete();
            $total += $deleted;
        } while ($deleted !== 0);

        return $total;
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    protected function table(string $table)
    {
        return DB::connection($this->connection)->table($table);
    }
}
