<?php

namespace Laravel\Telescope\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Telescope\Storage\DashboardAggregator;

class OutgoingRequestController extends Controller
{
    public function __construct(protected DashboardAggregator $aggregator)
    {
    }

    public function hosts(Request $request): JsonResponse
    {
        $hours = max(1, min(8760, (int) $request->input('hours', 24)));

        return response()->json([
            'hosts' => $this->aggregator->outgoingHosts($hours),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'take' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($this->aggregator->outgoingRequests(
            $validated['host'],
            (int) ($validated['hours'] ?? 24),
            (int) ($validated['take'] ?? 50),
        ));
    }
}
