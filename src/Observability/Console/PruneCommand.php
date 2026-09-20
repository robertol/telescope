<?php

namespace Laravel\Telescope\Observability\Console;

use Illuminate\Console\Command;
use Laravel\Telescope\Observability\ObservabilityTables;
use Laravel\Telescope\Observability\Storage\ObservabilityPruner;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'observability:prune')]
class PruneCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'observability:prune {--chunk= : Rows deleted per transaction}';

    /**
     * @var string
     */
    protected $description = 'Prune expired observability events in bounded batches';

    public function handle(ObservabilityPruner $pruner): int
    {
        $chunk = max(1, (int) ($this->option('chunk') ?: config('telescope.observability.prune_chunk', 5000)));
        $retention = config('telescope.observability.retention', []);

        $tables = [
            ObservabilityTables::REQUESTS => (int) ($retention['request'] ?? 30),
            ObservabilityTables::QUERIES => (int) ($retention['query'] ?? 7),
            ObservabilityTables::EXCEPTIONS => (int) ($retention['exception'] ?? 90),
            ObservabilityTables::JOBS => (int) ($retention['job'] ?? 14),
            ObservabilityTables::HTTP_CLIENT => (int) ($retention['http_client'] ?? 14),
            ObservabilityTables::CACHE => (int) ($retention['cache'] ?? 3),
            ObservabilityTables::LOGS => (int) ($retention['log'] ?? 7),
        ];

        $total = 0;

        foreach ($tables as $table => $days) {
            $total += $pruner->pruneTable($table, now()->subDays($days), $chunk);
        }

        $this->info($total.' observability entries pruned.');

        return self::SUCCESS;
    }
}
