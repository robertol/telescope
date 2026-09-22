<?php

namespace Laravel\Telescope\Storage;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Telescope\EntryType;

class DashboardAggregator
{
    public const SLOW_ROUTE_THRESHOLD_MS = 1000;

    public function __construct(protected string $connection)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(int $hours): array
    {
        $since = now()->subHours($hours);
        $requestRows = $this->entryScalars($since, EntryType::REQUEST, [
            'response_status',
            'duration',
            'method',
            'uri',
        ]);
        $slowRoutes = $this->slowRoutesFromRows($requestRows);

        return [
            'hours' => $hours,
            'requests' => $this->requestStatsFromRows($requestRows, $since, $hours),
            'exceptions' => [
                'total' => $this->table('telescope_entries')
                    ->where('type', EntryType::EXCEPTION)
                    ->where('created_at', '>=', $since)
                    ->count(),
                'users' => $this->exceptionUsers($since),
                'timeline' => $this->filledCountBuckets($since, $hours, EntryType::EXCEPTION),
            ],
            'jobs' => $this->jobStats($since, $hours),
            'slow_routes' => $slowRoutes['items'],
            'slow_routes_total' => $slowRoutes['total'],
            'slow_route_threshold_ms' => self::SLOW_ROUTE_THRESHOLD_MS,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function users(int $hours): array
    {
        $since = now()->subHours($hours);

        $rows = $this->table('telescope_entries_tags as tags')
            ->join('telescope_entries as entries', 'entries.uuid', '=', 'tags.entry_uuid')
            ->where('tags.tag', 'like', 'Auth:%')
            ->where('entries.created_at', '>=', $since)
            ->where('entries.type', EntryType::REQUEST)
            ->groupBy('tags.tag')
            ->orderByDesc(DB::raw('max(entries.created_at)'))
            ->get([
                'tags.tag',
                DB::raw('max(entries.created_at) as last_seen'),
                DB::raw('count(*) as requests'),
                DB::raw('max(entries.uuid) as latest_uuid'),
            ]);

        return $rows->map(function ($row) {
            $id = substr((string) $row->tag, 5);
            $entry = $this->table('telescope_entries')->where('uuid', $row->latest_uuid)->first();
            $content = is_string($entry?->content) ? json_decode($entry->content, true) : (array) ($entry->content ?? []);
            $user = $content['user'] ?? [];

            return [
                'id' => $id,
                'name' => $user['name'] ?? null,
                'email' => $user['email'] ?? null,
                'last_seen' => Carbon::parse($row->last_seen)->toDateTimeString(),
                'requests' => (int) $row->requests,
            ];
        })->all();
    }

    /**
     * Distinct request endpoints accessed by a client IP.
     *
     * @return array<int, array{method: string, uri: string, count: int}>
     */
    public function requestEndpointsByIp(string $ip): array
    {
        $method = $this->jsonValue('method');
        $uri = $this->jsonValue('uri');
        $ipAddress = $this->jsonValue('ip_address');

        $rows = $this->table('telescope_entries')
            ->where('type', EntryType::REQUEST)
            ->whereRaw("{$ipAddress} = ?", [$ip])
            ->groupBy(DB::raw($method), DB::raw($uri))
            ->orderByDesc(DB::raw('count(*)'))
            ->orderBy(DB::raw($method))
            ->orderBy(DB::raw($uri))
            ->get([
                DB::raw("{$method} as method"),
                DB::raw("{$uri} as uri"),
                DB::raw('count(*) as count'),
            ]);

        return $rows->map(fn ($row) => [
            'method' => (string) $row->method,
            'uri' => (string) $row->uri,
            'count' => (int) $row->count,
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function exceptionSummary(int $hours, ?string $status = null): array
    {
        $since = now()->subHours($hours);
        $families = $this->exceptionFamilies($since);

        $handled = $families->where('handled', true)->count();
        $unhandled = $families->where('handled', false)->count();

        if ($status === 'handled') {
            $families = $families->where('handled', true)->values();
        } elseif ($status === 'unhandled') {
            $families = $families->where('handled', false)->values();
        }

        return [
            'handled' => $handled,
            'unhandled' => $unhandled,
            'families' => $families->values()->all(),
            'timeline' => $this->filledCountBuckets($since, $hours, EntryType::EXCEPTION),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function exceptionImpact(string $familyHash): array
    {
        $query = $this->table('telescope_entries')
            ->where('type', EntryType::EXCEPTION)
            ->where('family_hash', $familyHash);

        $first = (clone $query)->orderBy('created_at')->first();
        $last = (clone $query)->orderByDesc('created_at')->first();

        $hostnames = (clone $query)
            ->select(DB::raw($this->jsonValue('hostname').' as hostname'))
            ->pluck('hostname')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $users = $this->table('telescope_entries_tags as tags')
            ->join('telescope_entries as entries', 'entries.uuid', '=', 'tags.entry_uuid')
            ->where('entries.type', EntryType::EXCEPTION)
            ->where('entries.family_hash', $familyHash)
            ->where('tags.tag', 'like', 'Auth:%')
            ->distinct()
            ->count('tags.tag');

        return [
            'first_seen' => $first ? Carbon::parse($first->created_at)->toDateTimeString() : null,
            'last_seen' => $last ? Carbon::parse($last->created_at)->toDateTimeString() : null,
            'events_24h' => (clone $query)->where('created_at', '>=', now()->subHours(24))->count(),
            'events_7d' => (clone $query)->where('created_at', '>=', now()->subDays(7))->count(),
            'events_30d' => (clone $query)->where('created_at', '>=', now()->subDays(30))->count(),
            'users' => $users,
            'hostnames' => $hostnames,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function outgoingHosts(int $hours): array
    {
        $since = now()->subHours($hours);

        return $this->table('telescope_entries_tags as tags')
            ->join('telescope_entries as entries', 'entries.uuid', '=', 'tags.entry_uuid')
            ->where('entries.type', EntryType::CLIENT_REQUEST)
            ->where('entries.created_at', '>=', $since)
            ->where('tags.tag', 'not like', 'Auth:%')
            ->groupBy('tags.tag')
            ->orderByDesc(DB::raw('count(*)'))
            ->get([
                'tags.tag as host',
                DB::raw('count(*) as total'),
            ])
            ->map(fn ($row) => [
                'host' => $row->host,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function outgoingRequests(string $host, int $hours, int $take = 50): array
    {
        $since = now()->subHours($hours);

        $uuids = $this->table('telescope_entries_tags')
            ->where('tag', $host)
            ->pluck('entry_uuid');

        $base = $this->table('telescope_entries')
            ->where('type', EntryType::CLIENT_REQUEST)
            ->where('created_at', '>=', $since)
            ->whereIn('uuid', $uuids);

        $rows = (clone $base)->orderByDesc('sequence')->get();
        $durations = $rows->map(fn ($row) => (int) ($this->content($row)['duration'] ?? 0))->filter()->values();
        $statuses = $rows->map(fn ($row) => (int) ($this->content($row)['response_status'] ?? 0));

        $entries = (clone $base)->orderByDesc('sequence')->limit($take)->get()->map(function ($row) {
            return [
                'id' => $row->uuid,
                'sequence' => $row->sequence,
                'created_at' => Carbon::parse($row->created_at)->toDateTimeString(),
                'content' => $this->content($row),
            ];
        })->all();

        return [
            'host' => $host,
            'requests' => [
                'total' => $rows->count(),
                'status' => $this->statusBuckets($statuses),
                'duration' => $this->durationStats($durations->all()),
                'buckets' => $this->statusDurationBuckets($rows, $since, $hours),
            ],
            'entries' => $entries,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function requestStatsFromRows(Collection $rows, DateTimeInterface $since, int $hours): array
    {
        $statuses = $rows->map(fn ($row) => $this->intValue($row, 'response_status'));
        $durations = $rows->map(fn ($row) => $this->intValue($row, 'duration'))->filter()->values()->all();

        return [
            'total' => $rows->count(),
            'status' => $this->statusBuckets($statuses),
            'duration' => $this->durationStats($durations),
            'buckets' => $this->statusDurationBuckets($rows, $since, $hours),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $statuses
     * @return array{2xx: int, 4xx: int, 5xx: int, 123xx: int}
     */
    protected function statusBuckets(Collection $statuses): array
    {
        $ok = $statuses->filter(fn ($status) => $status > 0 && $status < 400)->count();
        $four = $statuses->filter(fn ($status) => $status >= 400 && $status < 500)->count();
        $five = $statuses->filter(fn ($status) => $status >= 500)->count();

        return [
            '123xx' => $ok,
            '2xx' => $ok,
            '4xx' => $four,
            '5xx' => $five,
        ];
    }

    /**
     * @param  array<int, int|float>  $durations
     * @return array{min: int|null, max: int|null, avg: float|null, p95: float|null}
     */
    protected function durationStats(array $durations): array
    {
        if ($durations === []) {
            return ['min' => null, 'max' => null, 'avg' => null, 'p95' => null];
        }

        sort($durations);
        $index = (int) max(0, ceil(0.95 * count($durations)) - 1);

        return [
            'min' => (int) min($durations),
            'max' => (int) max($durations),
            'avg' => round(array_sum($durations) / count($durations), 2),
            'p95' => (float) $durations[$index],
        ];
    }

    /**
     * @return array{type: string, hours: int, total: int, timeline: array<int, array<string, mixed>>}
     */
    public function resourceSummary(string $type, int $hours): array
    {
        $since = now()->subHours($hours);
        $timeline = $this->filledCountBuckets($since, $hours, $type);
        $total = 0;

        foreach ($timeline as $point) {
            $total += (int) $point['total'];
        }

        return [
            'type' => $type,
            'hours' => $hours,
            'total' => $total,
            'timeline' => $timeline,
        ];
    }

    /**
     * @param  array<int, string>|null  $uuids
     * @return array<int, array{bucket: string, total: int}>
     */
    protected function countBuckets(DateTimeInterface $since, int $hours, string $type, ?array $uuids = null): array
    {
        $bucket = $this->sqlBucket($hours);

        $query = $this->table('telescope_entries')
            ->where('type', $type)
            ->where('created_at', '>=', $since);

        if ($uuids !== null) {
            $query->whereIn('uuid', $uuids);
        }

        return $query
            ->select(DB::raw($bucket.' as bucket'), DB::raw('count(*) as total'))
            ->groupByRaw($bucket)
            ->orderByRaw($bucket)
            ->get()
            ->map(fn ($row) => [
                'bucket' => (string) $row->bucket,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @return array<int, array{bucket: string, total: int}>
     */
    protected function filledCountBuckets(DateTimeInterface $since, int $hours, string $type): array
    {
        $filled = $this->emptyTimeline($since, $hours, []);

        foreach ($this->countBuckets($since, $hours, $type) as $point) {
            $key = $this->bucketKey($point['bucket'], $hours);

            if (! isset($filled[$key])) {
                continue;
            }

            $filled[$key]['total'] = (int) $point['total'];
        }

        return array_values(array_map(function (array $point) {
            unset($point['_durations']);

            return $point;
        }, $filled));
    }

    /**
     * @return array{processed: int, pending: int, failed: int, buckets: array<int, array<string, mixed>>}
     */
    protected function jobStats(DateTimeInterface $since, int $hours): array
    {
        $count = (int) $this->table('telescope_entries')
            ->where('type', EntryType::JOB)
            ->where('created_at', '>=', $since)
            ->count();

        if ($count > 5000) {
            return $this->jobVolumeStats($since, $hours, $count);
        }

        $rows = $this->entryScalars($since, EntryType::JOB, ['status']);

        $statuses = $rows->map(fn ($row) => $this->stringValue($row, 'status', 'pending'));
        $keys = ['processed', 'pending', 'failed'];
        $filled = $this->emptyTimeline($since, $hours, array_fill_keys($keys, 0));

        foreach ($rows as $row) {
            $key = $this->bucketKey($row->created_at, $hours);

            if (! isset($filled[$key])) {
                continue;
            }

            $status = $this->stringValue($row, 'status', 'pending');

            if (! in_array($status, $keys, true)) {
                $status = 'pending';
            }

            $filled[$key][$status]++;
            $filled[$key]['total']++;
        }

        return [
            'processed' => $statuses->filter(fn ($status) => $status === 'processed')->count(),
            'pending' => $statuses->filter(fn ($status) => $status === 'pending')->count(),
            'failed' => $statuses->filter(fn ($status) => $status === 'failed')->count(),
            'buckets' => array_values($filled),
        ];
    }

    /**
     * @return array{processed: int, pending: int, failed: int, buckets: array<int, array<string, mixed>>}
     */
    protected function jobVolumeStats(DateTimeInterface $since, int $hours, int $count): array
    {
        $keys = ['processed', 'pending', 'failed'];
        $filled = $this->emptyTimeline($since, $hours, array_fill_keys($keys, 0));

        foreach ($this->countBuckets($since, $hours, EntryType::JOB) as $point) {
            $key = $point['bucket'];

            if (! isset($filled[$key])) {
                continue;
            }

            $filled[$key]['processed'] = $point['total'];
            $filled[$key]['total'] = $point['total'];
        }

        return [
            'processed' => $count,
            'pending' => 0,
            'failed' => 0,
            'buckets' => array_values($filled),
        ];
    }

    protected function exceptionUsers(DateTimeInterface $since): int
    {
        return (int) $this->table('telescope_entries_tags as tags')
            ->join('telescope_entries as entries', 'entries.uuid', '=', 'tags.entry_uuid')
            ->where('entries.type', EntryType::EXCEPTION)
            ->where('entries.created_at', '>=', $since)
            ->where('tags.tag', 'like', 'Auth:%')
            ->distinct()
            ->count('tags.tag');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function statusDurationBuckets(Collection $rows, DateTimeInterface $since, int $hours): array
    {
        $template = ['123xx' => 0, '4xx' => 0, '5xx' => 0];
        $filled = $this->emptyTimeline($since, $hours, $template);

        foreach ($rows as $row) {
            $key = $this->bucketKey($row->created_at, $hours);

            if (! isset($filled[$key])) {
                continue;
            }

            $band = $this->statusBand($this->intValue($row, 'response_status'));

            if ($band !== null) {
                $filled[$key][$band]++;
            }

            $filled[$key]['total']++;
            $duration = $this->intValue($row, 'duration');

            if ($duration > 0) {
                $filled[$key]['_durations'][] = $duration;
            }
        }

        return array_values(array_map(function (array $point) {
            $stats = $this->durationStats($point['_durations'] ?? []);
            unset($point['_durations']);
            $point['avg'] = $stats['avg'];
            $point['p95'] = $stats['p95'];
            $point['min'] = $stats['min'];
            $point['max'] = $stats['max'];

            return $point;
        }, $filled));
    }

    /**
     * @param  array<string, int>  $template
     * @return array<string, array<string, mixed>>
     */
    protected function emptyTimeline(DateTimeInterface $since, int $hours, array $template): array
    {
        $minute = $this->isMinuteResolution($hours);
        $cursor = Carbon::parse($since)->utc();
        $cursor = $minute ? $cursor->startOfMinute() : $cursor->startOfHour();
        $end = $minute ? now()->utc()->startOfMinute() : now()->utc()->startOfHour();
        $points = [];

        while ($cursor->lte($end)) {
            $key = $this->bucketKey($cursor, $hours);
            $points[$key] = array_merge([
                'bucket' => $key,
                'total' => 0,
                '_durations' => [],
            ], $template);
            $minute ? $cursor->addMinute() : $cursor->addHour();
        }

        return $points;
    }

    protected function bucketKey(mixed $time, int $hours): string
    {
        $carbon = Carbon::parse($time)->utc();

        return $this->isMinuteResolution($hours)
            ? $carbon->format('Y-m-d H:i:00')
            : $carbon->format('Y-m-d H:00:00');
    }

    protected function isMinuteResolution(int $hours): bool
    {
        return $hours <= 1;
    }

    protected function statusBand(int $status): ?string
    {
        if ($status >= 500) {
            return '5xx';
        }

        if ($status >= 400) {
            return '4xx';
        }

        if ($status > 0) {
            return '123xx';
        }

        return null;
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    protected function slowRoutesFromRows(Collection $rows): array
    {
        $routes = $rows
            ->filter(fn ($row) => $this->intValue($row, 'duration') > self::SLOW_ROUTE_THRESHOLD_MS)
            ->groupBy(fn ($row) => $this->stringValue($row, 'method', 'GET').' '.$this->stringValue($row, 'uri', '/'))
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'method' => $this->stringValue($first, 'method', 'GET'),
                    'uri' => $this->stringValue($first, 'uri', '/'),
                    'count' => $group->count(),
                    'max_duration' => (int) $group->max(fn ($row) => $this->intValue($row, 'duration')),
                ];
            })
            ->sortByDesc('max_duration')
            ->values();

        return [
            'items' => $routes->take(4)->values()->all(),
            'total' => $routes->count(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function exceptionFamilies(DateTimeInterface $since): Collection
    {
        $rows = $this->table('telescope_entries')
            ->where('type', EntryType::EXCEPTION)
            ->where('created_at', '>=', $since)
            ->whereNotNull('family_hash')
            ->orderByDesc('created_at')
            ->orderByDesc('sequence')
            ->get([
                'uuid',
                'sequence',
                'family_hash',
                'created_at',
                DB::raw($this->jsonValue('class').' as class'),
                DB::raw($this->jsonValue('message').' as message'),
                DB::raw($this->jsonValue('resolved_at').' as resolved_at'),
            ]);

        return $rows->groupBy('family_hash')->map(function (Collection $group, $hash) {
            $latest = $group->sortByDesc(function ($row) {
                return Carbon::parse($row->created_at)->timestamp.':'.str_pad((string) $row->sequence, 10, '0', STR_PAD_LEFT);
            })->first();
            $uuids = $group->pluck('uuid');

            $users = $this->table('telescope_entries_tags')
                ->whereIn('entry_uuid', $uuids)
                ->where('tag', 'like', 'Auth:%')
                ->distinct()
                ->count('tag');

            $resolvedAt = $this->stringValue($latest, 'resolved_at');

            return [
                'family_hash' => $hash,
                'latest_id' => $latest->uuid,
                'class' => $this->stringValue($latest, 'class') ?: null,
                'message' => $this->stringValue($latest, 'message') ?: null,
                'count' => $group->count(),
                'users' => $users,
                'last_seen' => Carbon::parse($latest->created_at)->toDateTimeString(),
                'handled' => $resolvedAt !== '',
            ];
        })->values();
    }

    /**
     * @param  array<int, string>  $keys
     * @return Collection<int, object>
     */
    protected function entryScalars(DateTimeInterface $since, string $type, array $keys): Collection
    {
        $columns = ['created_at'];

        foreach ($keys as $key) {
            $columns[] = DB::raw($this->jsonValue($key).' as '.$key);
        }

        return $this->table('telescope_entries')
            ->where('type', $type)
            ->where('created_at', '>=', $since)
            ->get($columns);
    }

    protected function intValue(object $row, string $key): int
    {
        if (isset($row->{$key}) && $row->{$key} !== '' && $row->{$key} !== null) {
            return (int) $row->{$key};
        }

        return (int) ($this->content($row)[$key] ?? 0);
    }

    protected function stringValue(object $row, string $key, string $default = ''): string
    {
        $value = $row->{$key} ?? null;

        if (is_string($value) && $value !== '') {
            return $value;
        }

        $fromContent = $this->content($row)[$key] ?? null;

        if (is_string($fromContent) && $fromContent !== '') {
            return $fromContent;
        }

        return $default;
    }

    /**
     * @param  object  $row
     * @return array<string, mixed>
     */
    protected function content($row): array
    {
        if (is_array($row->content ?? null)) {
            return $row->content;
        }

        $decoded = json_decode((string) ($row->content ?? '{}'), true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function jsonValue(string $key): string
    {
        if ($this->isPgsql()) {
            return "(content::jsonb)->>'{$key}'";
        }

        return "json_extract(content, '$.{$key}')";
    }

    protected function isPgsql(): bool
    {
        return DB::connection($this->connection)->getDriverName() === 'pgsql';
    }

    protected function sqlBucket(int $hours): string
    {
        return $this->isMinuteResolution($hours)
            ? $this->minuteBucket()
            : $this->hourBucket();
    }

    protected function hourBucket(): string
    {
        $driver = DB::connection($this->connection)->getDriverName();

        if ($driver === 'pgsql') {
            return "to_char(date_trunc('hour', created_at), 'YYYY-MM-DD HH24:00:00')";
        }

        return "strftime('%Y-%m-%d %H:00:00', created_at)";
    }

    protected function minuteBucket(): string
    {
        $driver = DB::connection($this->connection)->getDriverName();

        if ($driver === 'pgsql') {
            return "to_char(date_trunc('minute', created_at), 'YYYY-MM-DD HH24:MI:00')";
        }

        return "strftime('%Y-%m-%d %H:%M:00', created_at)";
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    protected function table(string $table)
    {
        return DB::connection($this->connection)->table($table);
    }
}
