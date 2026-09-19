<?php

namespace Laravel\Telescope\Contracts;

interface ClearableRepository
{
    /**
     * Clear all of the entries.
     *
     * @param  bool  $preserveMonitoring
     * @return void
     */
    public function clear($preserveMonitoring = false);
}
