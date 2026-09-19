<?php

namespace Laravel\Telescope\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Telescope\Storage\DashboardAggregator;

class ExceptionSummaryController extends Controller
{
    public function __construct(protected DashboardAggregator $aggregator)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $hours = max(1, min(8760, (int) $request->input('hours', 336)));
        $status = $request->input('status');

        if (! in_array($status, [null, 'handled', 'unhandled'], true)) {
            $status = null;
        }

        return response()->json($this->aggregator->exceptionSummary($hours, $status));
    }
}
