<?php

namespace Laravel\Telescope\Observability;

use Illuminate\Support\Arr;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\IncomingEntry;

class ObservabilityNormalizer
{
    public function __construct(
        protected ObservabilitySanitizer $sanitizer = new ObservabilitySanitizer,
        protected QueryFingerprint $queryFingerprint = new QueryFingerprint,
        protected ExceptionFingerprint $exceptionFingerprint = new ExceptionFingerprint,
    ) {
    }

    /**
     * @return array{table: string, type: string, row: array<string, mixed>}|null
     */
    public function normalize(IncomingEntry $entry): ?array
    {
        $table = ObservabilityTables::forType((string) $entry->type);

        if ($table === null) {
            return null;
        }

        $content = $this->sanitizer->sanitize(is_array($entry->content) ? $entry->content : []);

        $row = match ($entry->type) {
            EntryType::REQUEST => $this->request($entry, $content),
            EntryType::QUERY => $this->query($entry, $content),
            EntryType::EXCEPTION => $this->exception($entry, $content),
            EntryType::JOB => $this->job($entry, $content),
            EntryType::CLIENT_REQUEST => $this->httpClient($entry, $content),
            EntryType::CACHE => $this->cache($entry, $content),
            EntryType::LOG => $this->log($entry, $content),
            default => null,
        };

        if ($row === null) {
            return null;
        }

        return [
            'table' => $table,
            'type' => $entry->type,
            'row' => $row,
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    protected function request(IncomingEntry $entry, array $content): array
    {
        $path = $this->pathFromUri((string) ($content['uri'] ?? '/'));

        return $this->base($entry, $content) + [
            'method' => (string) ($content['method'] ?? 'GET'),
            'route' => $content['route'] ?? $path,
            'uri' => $path,
            'status_code' => (int) ($content['response_status'] ?? 0),
            'duration_ms' => $this->intOrNull($content['duration'] ?? null),
            'memory_peak' => isset($content['memory']) ? (float) $content['memory'] : null,
            'user_id' => $this->userId($content),
            'ip' => $content['ip_address'] ?? null,
            'content' => Arr::except($content, [
                'method', 'uri', 'route', 'response_status', 'duration', 'memory', 'ip_address', 'user', 'hostname',
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    protected function query(IncomingEntry $entry, array $content): array
    {
        $sql = (string) ($content['sql'] ?? '');
        $normalized = $this->queryFingerprint->normalize($sql);

        return $this->base($entry, $content) + [
            'connection' => $content['connection'] ?? null,
            'query_hash' => $this->queryFingerprint->hash($sql, isset($content['hash']) ? (string) $content['hash'] : null),
            'sql' => $normalized !== '' ? $normalized : $sql,
            'duration_ms' => $this->intOrNull($content['time'] ?? null),
            'content' => Arr::except($content, ['connection', 'sql', 'time', 'hash', 'hostname']),
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    protected function exception(IncomingEntry $entry, array $content): array
    {
        $class = (string) ($content['class'] ?? 'Exception');
        $file = (string) ($content['file'] ?? '');
        $line = (int) ($content['line'] ?? 0);

        return $this->base($entry, $content) + [
            'exception_class' => $class,
            'message' => (string) ($content['message'] ?? ''),
            'file' => $file,
            'line' => $line,
            'exception_hash' => $this->exceptionFingerprint->hash($class, $file, $line, $content['message'] ?? null),
            'content' => Arr::except($content, ['class', 'message', 'file', 'line', 'hostname']),
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    protected function job(IncomingEntry $entry, array $content): array
    {
        return $this->base($entry, $content) + [
            'queue' => $content['queue'] ?? null,
            'connection' => $content['connection'] ?? null,
            'job_class' => $content['name'] ?? null,
            'status' => $content['status'] ?? 'pending',
            'duration_ms' => $this->intOrNull($content['duration'] ?? null),
            'attempts' => $this->intOrNull($content['attempts'] ?? $content['tries'] ?? null),
            'content' => Arr::except($content, ['queue', 'connection', 'name', 'status', 'duration', 'attempts', 'tries', 'hostname']),
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    protected function httpClient(IncomingEntry $entry, array $content): array
    {
        $parts = $this->parseUrl((string) ($content['uri'] ?? ''));

        return $this->base($entry, $content) + [
            'method' => (string) ($content['method'] ?? 'GET'),
            'host' => $parts['host'],
            'path' => $parts['path'],
            'status_code' => $this->intOrNull($content['response_status'] ?? null),
            'duration_ms' => $this->intOrNull($content['duration'] ?? null),
            'content' => Arr::except($content, ['method', 'uri', 'response_status', 'duration', 'hostname']),
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    protected function cache(IncomingEntry $entry, array $content): array
    {
        $operation = (string) ($content['type'] ?? 'hit');
        $key = (string) ($content['key'] ?? '');

        return $this->base($entry, $content) + [
            'operation' => $operation,
            'key_hash' => hash('sha256', $key),
            'store' => $content['store'] ?? null,
            'hit' => $operation === 'hit',
            'duration_ms' => $this->intOrNull($content['duration'] ?? null),
            'content' => Arr::except($content, ['type', 'key', 'store', 'hostname']),
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    protected function log(IncomingEntry $entry, array $content): array
    {
        return $this->base($entry, $content) + [
            'level' => (string) ($content['level'] ?? 'info'),
            'message' => (string) ($content['message'] ?? ''),
            'content' => Arr::except($content, ['level', 'message', 'hostname']),
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    protected function base(IncomingEntry $entry, array $content): array
    {
        return [
            'uuid' => $entry->uuid,
            'batch_id' => $entry->batchId,
            'trace_id' => $entry->traceId,
            'span_id' => $entry->spanId,
            'parent_span_id' => $entry->parentSpanId,
            'environment' => app()->environment(),
            'hostname' => $content['hostname'] ?? gethostname(),
            'created_at' => $entry->recordedAt?->toDateTimeString(),
        ];
    }

    protected function pathFromUri(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);

        if (is_string($path) && $path !== '') {
            return $path;
        }

        $stripped = strtok($uri, '?');

        return is_string($stripped) && $stripped !== '' ? $stripped : '/';
    }

    /**
     * @return array{host: string|null, path: string}
     */
    protected function parseUrl(string $uri): array
    {
        $parts = parse_url($uri);

        return [
            'host' => is_array($parts) ? ($parts['host'] ?? null) : null,
            'path' => is_array($parts) && ! empty($parts['path']) ? $parts['path'] : '/',
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function userId(array $content): int|string|null
    {
        $id = $content['user']['id'] ?? null;

        if ($id === null || $id === '') {
            return null;
        }

        return is_numeric($id) ? (int) $id : $id;
    }

    protected function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) round((float) $value);
    }
}
