<?php

namespace Laravel\Telescope\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\DashboardAggregator;
use Laravel\Telescope\Watchers\RequestWatcher;

class RequestsController extends EntryController
{
    /**
     * The entry type for the controller.
     *
     * @return string
     */
    protected function entryType()
    {
        return EntryType::REQUEST;
    }

    /**
     * The watcher class for the controller.
     *
     * @return string
     */
    protected function watcher()
    {
        return RequestWatcher::class;
    }

    /**
     * Distinct endpoints accessed by a client IP.
     */
    public function endpoints(Request $request, DashboardAggregator $aggregator): JsonResponse
    {
        $validated = $request->validate([
            'ip' => ['required', 'ip'],
        ]);

        return response()->json([
            'endpoints' => $aggregator->requestEndpointsByIp($validated['ip']),
        ]);
    }
}
