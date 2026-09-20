<?php

namespace Laravel\Telescope\Observability\Storage;

use DateTimeInterface;
use Illuminate\Support\Collection;
use Laravel\Telescope\Contracts\ClearableRepository;
use Laravel\Telescope\Contracts\EntriesRepository as Contract;
use Laravel\Telescope\Contracts\PrunableRepository;
use Laravel\Telescope\Contracts\TerminableRepository;
use Laravel\Telescope\EntryResult;
use Laravel\Telescope\Observability\ObservabilityCollector;
use Laravel\Telescope\Storage\DatabaseEntriesRepository;
use Laravel\Telescope\Storage\EntryQueryOptions;

class ObservabilityEntriesRepository implements Contract, ClearableRepository, PrunableRepository, TerminableRepository
{
    public function __construct(
        protected DatabaseEntriesRepository $legacy,
        protected ObservabilityCollector $collector,
    ) {
    }

    public function find($id): EntryResult
    {
        return $this->legacy->find($id);
    }

    public function get($type, EntryQueryOptions $options)
    {
        return $this->legacy->get($type, $options);
    }

    public function store(Collection $entries)
    {
        if (config('telescope.observability.storage_enabled')) {
            $this->collector->collect($entries);
        }

        if (config('telescope.observability.legacy_storage', true)) {
            $this->legacy->store($entries);
        }
    }

    public function update(Collection $updates)
    {
        if (config('telescope.observability.storage_enabled')) {
            $this->collector->applyUpdates($updates);
        }

        if (config('telescope.observability.legacy_storage', true)) {
            return $this->legacy->update($updates);
        }

        return collect();
    }

    public function loadMonitoredTags()
    {
        $this->legacy->loadMonitoredTags();
    }

    public function isMonitoring(array $tags)
    {
        return $this->legacy->isMonitoring($tags);
    }

    public function monitoring()
    {
        return $this->legacy->monitoring();
    }

    public function monitor(array $tags)
    {
        $this->legacy->monitor($tags);
    }

    public function stopMonitoring(array $tags)
    {
        $this->legacy->stopMonitoring($tags);
    }

    public function loadMonitoredEndpoints()
    {
        $this->legacy->loadMonitoredEndpoints();
    }

    public function isMonitoringEndpoint(string $uri, string $method)
    {
        return $this->legacy->isMonitoringEndpoint($uri, $method);
    }

    public function monitoringEndpoints()
    {
        return $this->legacy->monitoringEndpoints();
    }

    public function monitorEndpoints(array $endpoints)
    {
        $this->legacy->monitorEndpoints($endpoints);
    }

    public function stopMonitoringEndpoints(array $endpoints)
    {
        $this->legacy->stopMonitoringEndpoints($endpoints);
    }

    public function prune(DateTimeInterface $before, $keepExceptions)
    {
        return $this->legacy->prune($before, $keepExceptions);
    }

    public function clear($preserveMonitoring = false)
    {
        $this->legacy->clear($preserveMonitoring);
    }

    public function terminate()
    {
        $this->legacy->terminate();
    }
}
