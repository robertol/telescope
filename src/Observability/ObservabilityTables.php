<?php

namespace Laravel\Telescope\Observability;

use Laravel\Telescope\EntryType;

class ObservabilityTables
{
    public const REQUESTS = 'observability_requests';
    public const QUERIES = 'observability_queries';
    public const EXCEPTIONS = 'observability_exceptions';
    public const JOBS = 'observability_jobs';
    public const HTTP_CLIENT = 'observability_http_client';
    public const CACHE = 'observability_cache';
    public const LOGS = 'observability_logs';

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            EntryType::REQUEST => self::REQUESTS,
            EntryType::QUERY => self::QUERIES,
            EntryType::EXCEPTION => self::EXCEPTIONS,
            EntryType::JOB => self::JOBS,
            EntryType::CLIENT_REQUEST => self::HTTP_CLIENT,
            EntryType::CACHE => self::CACHE,
            EntryType::LOG => self::LOGS,
        ];
    }

    public static function forType(string $type): ?string
    {
        return self::all()[$type] ?? null;
    }
}
