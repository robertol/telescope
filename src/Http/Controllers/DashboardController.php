<?php

namespace Laravel\Telescope\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Laravel\Telescope\Storage\DashboardAggregator;

class DashboardController extends Controller
{
    public function __construct(protected DashboardAggregator $aggregator)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $hours = $this->hours($request);

        $payload = Cache::remember('telescope:dashboard:'.$hours, 30, fn () => $this->aggregator->dashboard($hours));

        return response()->json($payload);
    }

    public function settings(): JsonResponse
    {
        $watchers = collect(config('telescope.watchers', []))->map(function ($options, $class) {
            $enabled = $options === true || (is_array($options) && ($options['enabled'] ?? true));

            return [
                'class' => $class,
                'name' => class_basename($class),
                'enabled' => (bool) $enabled,
            ];
        })->values();

        return response()->json([
            'recording' => ! cache('telescope:pause-recording', false),
            'watchers' => $watchers,
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        return response()->json([
            'users' => $this->aggregator->users($this->hours($request)),
        ]);
    }

    private function hours(Request $request): int
    {
        $hours = (int) $request->input('hours', 336);

        return max(1, min(8760, $hours));
    }
}
