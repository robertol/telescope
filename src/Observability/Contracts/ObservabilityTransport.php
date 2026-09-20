<?php

namespace Laravel\Telescope\Observability\Contracts;

use Illuminate\Support\Collection;

interface ObservabilityTransport
{
    /**
     * Persist a collection of normalized observability rows.
     *
     * @param  \Illuminate\Support\Collection<int, array{table: string, type: string, row: array<string, mixed>}>  $entries
     */
    public function send(Collection $entries): void;
}
