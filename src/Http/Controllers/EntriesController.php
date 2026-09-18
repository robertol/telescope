<?php

namespace Laravel\Telescope\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Telescope\Contracts\ClearableRepository;
use Laravel\Telescope\Storage\DatabaseEntriesRepository;

class EntriesController extends Controller
{
    /**
     * Delete all of the entries from storage.
     *
     * @param  \Laravel\Telescope\Contracts\ClearableRepository  $storage
     * @return void
     */
    public function destroy(ClearableRepository $storage)
    {
        $storage->clear();
    }

    /**
     * Tell the dashboard whether a prune of this age is small enough to run.
     */
    public function pruneStatus(Request $request, DatabaseEntriesRepository $storage): JsonResponse
    {
        $before = now()->subHours($this->validatedPruneHours($request));
        $limit = $this->httpPruneLimit();

        return response()->json([
            'allowed' => ! $storage->pruneExceedsLimit($before, $limit),
            'limit' => $limit,
        ]);
    }

    /**
     * Prune entries older than the given number of hours.
     *
     * Monitored endpoint batches stay, because that rule lives in the repository.
     * Refuses when the matching set is large enough to hold a web worker.
     */
    public function prune(Request $request, DatabaseEntriesRepository $storage): JsonResponse
    {
        $before = now()->subHours($this->validatedPruneHours($request));
        $limit = $this->httpPruneLimit();

        if ($storage->pruneExceedsLimit($before, $limit)) {
            return response()->json([
                'message' => 'Prune recusado: mais de '.$limit.' registros nesse período.',
            ], 409);
        }

        set_time_limit(0);

        return response()->json([
            'pruned' => $storage->prune($before, false),
        ]);
    }

    private function validatedPruneHours(Request $request): int
    {
        $validated = $request->validate([
            'hours' => ['required', 'integer', 'min:1', 'max:8760'],
        ]);

        return (int) $validated['hours'];
    }

    private function httpPruneLimit(): int
    {
        return max(1, (int) config('telescope.prune_request_limit', 20000));
    }
}
