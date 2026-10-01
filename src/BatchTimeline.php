<?php

namespace Laravel\Telescope;

use Illuminate\Support\Collection;

class BatchTimeline
{
    /**
     * Build a chronological timeline from batch entries.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Telescope\EntryResult>|array<int, \Laravel\Telescope\EntryResult>  $batch
     * @return array<int, array<string, mixed>>
     */
    public static function from(Collection|array $batch): array
    {
        return Collection::make($batch)
            ->sortBy(fn (EntryResult $entry) => $entry->sequence ?? 0)
            ->values()
            ->map(fn (EntryResult $entry) => [
                'id' => $entry->id,
                'type' => $entry->type,
                'sequence' => $entry->sequence,
                'created_at' => optional($entry->createdAt)->toDateTimeString(),
                'summary' => self::summary($entry),
                'duration' => self::duration($entry),
            ])
            ->all();
    }

    protected static function summary(EntryResult $entry): string
    {
        $content = $entry->content;

        return match ($entry->type) {
            EntryType::REQUEST, EntryType::CLIENT_REQUEST => trim(($content['method'] ?? 'GET').' '.($content['uri'] ?? '/')),
            EntryType::QUERY => (string) ($content['sql'] ?? 'query'),
            EntryType::CACHE => trim(($content['type'] ?? 'cache').' '.($content['key'] ?? '')),
            EntryType::EXCEPTION => (string) ($content['class'] ?? 'Exception'),
            EntryType::LOG => (string) ($content['message'] ?? 'log'),
            EntryType::JOB => (string) ($content['name'] ?? 'job'),
            EntryType::MAIL => (string) ($content['mailable'] ?? 'mail'),
            EntryType::NOTIFICATION => (string) ($content['notification'] ?? 'notification'),
            EntryType::MODEL => trim(($content['action'] ?? 'model').' '.($content['model'] ?? '')),
            EntryType::EVENT => (string) ($content['name'] ?? 'event'),
            EntryType::GATE => (string) ($content['ability'] ?? 'gate'),
            EntryType::REDIS => (string) ($content['command'] ?? 'redis'),
            EntryType::VIEW => (string) ($content['name'] ?? 'view'),
            EntryType::COMMAND => (string) ($content['command'] ?? 'command'),
            default => (string) $entry->type,
        };
    }

    protected static function duration(EntryResult $entry): ?string
    {
        $content = $entry->content;

        return match ($entry->type) {
            EntryType::REQUEST, EntryType::CLIENT_REQUEST => isset($content['duration']) ? $content['duration'].'ms' : null,
            EntryType::QUERY, EntryType::REDIS => isset($content['time']) ? $content['time'].'ms' : null,
            default => null,
        };
    }
}
