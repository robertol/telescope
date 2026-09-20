<?php

namespace Laravel\Telescope\Observability\Transport;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\EntryUpdate;
use Laravel\Telescope\Observability\Contracts\ObservabilityTransport;
use Laravel\Telescope\Observability\ObservabilityTables;

class PostgresTransport implements ObservabilityTransport
{
    public function __construct(
        protected string $connection,
        protected int $chunkSize = 1000,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function send(Collection $entries): void
    {
        $entries
            ->groupBy('table')
            ->each(function (Collection $group, string $table) {
                $group->chunk($this->chunkSize)->each(function (Collection $chunk) use ($table) {
                    $this->table($table)->insert(
                        $chunk->map(fn (array $entry) => $this->encode($entry['row']))->all()
                    );
                });
            });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Laravel\Telescope\EntryUpdate>  $updates
     */
    public function applyUpdates(Collection $updates): void
    {
        $updates
            ->where('type', EntryType::JOB)
            ->each(function (EntryUpdate $update) {
                $changes = [];

                if (array_key_exists('status', $update->changes)) {
                    $changes['status'] = $update->changes['status'];
                }

                if ($changes === []) {
                    return;
                }

                $query = $this->table(ObservabilityTables::JOBS)->where('uuid', $update->uuid);

                if (($changes['status'] ?? null) === 'processed' || ($changes['status'] ?? null) === 'failed') {
                    $existing = $query->first();

                    if ($existing && empty($existing->duration_ms) && $existing->created_at) {
                        $changes['duration_ms'] = (int) max(0, now()->diffInMilliseconds($existing->created_at));
                    }
                }

                $this->table(ObservabilityTables::JOBS)
                    ->where('uuid', $update->uuid)
                    ->update($changes);
            });
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function encode(array $row): array
    {
        if (array_key_exists('content', $row) && is_array($row['content'])) {
            $row['content'] = json_encode($row['content'], JSON_INVALID_UTF8_SUBSTITUTE);
        }

        if (array_key_exists('hit', $row)) {
            $row['hit'] = $row['hit'] ? 1 : 0;
        }

        return $row;
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    protected function table(string $table)
    {
        return DB::connection($this->connection)->table($table);
    }
}
