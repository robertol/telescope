<?php

namespace Laravel\Telescope\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\DashboardAggregator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResourceSummaryController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const TYPES = [
        'request' => EntryType::REQUEST,
        'job' => EntryType::JOB,
        'query' => EntryType::QUERY,
        'log' => EntryType::LOG,
    ];

    public function __construct(protected DashboardAggregator $aggregator)
    {
    }

    public function show(Request $request, string $type): JsonResponse
    {
        if (! isset(self::TYPES[$type])) {
            throw new NotFoundHttpException;
        }

        $hours = max(1, min(8760, (int) $request->input('hours', 24)));
        $key = 'telescope:summary:'.$type.':'.$hours;

        if ($request->boolean('fresh')) {
            Cache::forget($key);
        }

        $payload = Cache::remember(
            $key,
            60,
            fn () => $this->aggregator->resourceSummary(self::TYPES[$type], $hours)
        );

        $payload['type'] = $type;

        return response()->json($payload);
    }
}
