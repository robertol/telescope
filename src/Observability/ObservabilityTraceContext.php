<?php

namespace Laravel\Telescope\Observability;

use Illuminate\Support\Str;
use Laravel\Telescope\EntryType;

class ObservabilityTraceContext
{
    protected ?string $traceId = null;

    protected ?string $rootSpanId = null;

    public function start(): void
    {
        $this->traceId = (string) Str::orderedUuid();
        $this->rootSpanId = (string) Str::orderedUuid();
    }

    public function reset(): void
    {
        $this->traceId = null;
        $this->rootSpanId = null;
    }

    public function isStarted(): bool
    {
        return $this->traceId !== null && $this->rootSpanId !== null;
    }

    /**
     * @return array{trace_id: string, span_id: string, parent_span_id: string|null}
     */
    public function spanFor(string $type): array
    {
        if (! $this->isStarted()) {
            $this->start();
        }

        if ($type === EntryType::REQUEST) {
            return [
                'trace_id' => $this->traceId,
                'span_id' => $this->rootSpanId,
                'parent_span_id' => null,
            ];
        }

        return [
            'trace_id' => $this->traceId,
            'span_id' => (string) Str::orderedUuid(),
            'parent_span_id' => $this->rootSpanId,
        ];
    }
}
