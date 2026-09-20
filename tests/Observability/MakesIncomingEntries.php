<?php

namespace Laravel\Telescope\Tests\Observability;

use Illuminate\Support\Str;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\IncomingExceptionEntry;
use RuntimeException;

trait MakesIncomingEntries
{
    protected function incoming(string $type, array $content = [], ?string $batchId = null): IncomingEntry
    {
        return IncomingEntry::make($content + ['hostname' => 'testhost'])
            ->type($type)
            ->batchId($batchId ?? (string) Str::orderedUuid());
    }

    protected function incomingRequest(array $content = []): IncomingEntry
    {
        return $this->incoming(EntryType::REQUEST, $content + [
            'method' => 'GET',
            'uri' => '/v1/cards?token=secret',
            'route' => 'cards.index',
            'response_status' => 200,
            'duration' => 42,
            'memory' => 8.5,
            'ip_address' => '127.0.0.1',
            'headers' => ['authorization' => 'Bearer abc'],
            'payload' => ['password' => 'secret'],
            'response' => ['ok' => true],
            'user' => ['id' => 9, 'email' => 'a@example.com'],
        ]);
    }

    protected function incomingQuery(array $content = []): IncomingEntry
    {
        return $this->incoming(EntryType::QUERY, $content + [
            'connection' => 'pgsql',
            'sql' => 'select * from users where id = 10',
            'time' => '12.40',
            'slow' => false,
            'hash' => md5('select * from users where id = ?'),
            'file' => 'User.php',
            'line' => 20,
        ]);
    }

    protected function incomingException(array $content = []): IncomingExceptionEntry
    {
        $content = $content + [
            'class' => RuntimeException::class,
            'file' => '/app/Foo.php',
            'line' => 12,
            'message' => 'Boom',
            'trace' => [['file' => '/app/Foo.php', 'line' => 12]],
        ];

        return IncomingExceptionEntry::make(new RuntimeException('Boom'), $content)
            ->type(EntryType::EXCEPTION)
            ->batchId((string) Str::orderedUuid());
    }

    protected function incomingJob(array $content = []): IncomingEntry
    {
        return $this->incoming(EntryType::JOB, $content + [
            'status' => 'processed',
            'connection' => 'redis',
            'queue' => 'default',
            'name' => 'App\\Jobs\\ChargeCard',
            'tries' => 3,
            'data' => ['card_number' => '4111111111111111'],
        ]);
    }

    protected function incomingClientRequest(array $content = []): IncomingEntry
    {
        return $this->incoming(EntryType::CLIENT_REQUEST, $content + [
            'method' => 'POST',
            'uri' => 'https://api.starkbank.com/v2/invoice?api_key=secret',
            'response_status' => 201,
            'duration' => 120,
            'headers' => ['authorization' => 'Bearer xyz'],
            'payload' => ['amount' => 10],
        ]);
    }

    protected function incomingCache(array $content = []): IncomingEntry
    {
        return $this->incoming(EntryType::CACHE, $content + [
            'type' => 'hit',
            'key' => 'user:42:profile',
            'value' => ['token' => 'abc'],
        ]);
    }

    protected function incomingLog(array $content = []): IncomingEntry
    {
        return $this->incoming(EntryType::LOG, $content + [
            'level' => 'error',
            'message' => 'Payment failed',
            'context' => ['cvv' => '123', 'order' => 1],
        ]);
    }
}
