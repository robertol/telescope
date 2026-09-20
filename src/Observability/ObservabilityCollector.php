<?php

namespace Laravel\Telescope\Observability;

use Illuminate\Support\Collection;
use Laravel\Telescope\EntryUpdate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Observability\Contracts\ObservabilityTransport;

class ObservabilityCollector
{
    public function __construct(
        protected ObservabilityNormalizer $normalizer,
        protected ObservabilityTransport $transport,
        protected ObservabilityTraceContext $trace,
    ) {
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Laravel\Telescope\IncomingEntry>  $entries
     */
    public function collect(Collection $entries): void
    {
        $normalized = $entries
            ->map(fn (IncomingEntry $entry) => $this->stampTrace($entry))
            ->map(fn (IncomingEntry $entry) => $this->normalizer->normalize($entry))
            ->filter()
            ->values();

        if ($normalized->isEmpty()) {
            return;
        }

        $this->transport->send($normalized);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Laravel\Telescope\EntryUpdate>  $updates
     */
    public function applyUpdates(Collection $updates): void
    {
        if (method_exists($this->transport, 'applyUpdates')) {
            $this->transport->applyUpdates($updates);
        }
    }

    protected function stampTrace(IncomingEntry $entry): IncomingEntry
    {
        if ($entry->traceId) {
            return $entry;
        }

        $span = $this->trace->spanFor((string) $entry->type);

        return $entry->withTrace($span['trace_id'], $span['span_id'], $span['parent_span_id']);
    }
}
