<?php

namespace Laravel\Telescope\Tests\Http;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Tests\Console\CreatesTelescopeEntries;
use Laravel\Telescope\Tests\FeatureTestCase;
use Orchestra\Testbench\Http\Middleware\VerifyCsrfToken;

class RequestTimelineTest extends FeatureTestCase
{
    use CreatesTelescopeEntries;

    /** {@inheritdoc} */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([Authorize::class, VerifyCsrfToken::class, ValidateCsrfToken::class, PreventRequestForgery::class]);
    }

    public function test_request_show_returns_batch_timeline_sorted_chronologically(): void
    {
        $batchId = '11111111-1111-1111-1111-111111111111';

        $request = $this->createRequest([
            'method' => 'GET',
            'uri' => '/v1/cards',
        ], [
            'batch_id' => $batchId,
            'sequence' => 30,
        ]);

        $this->createEntry(EntryType::QUERY, [
            'sql' => 'select * from cards',
            'time' => 12.5,
            'connection' => 'pgsql',
            'slow' => false,
            'hash' => 'abc',
        ], [
            'batch_id' => $batchId,
            'sequence' => 10,
        ]);

        $this->createEntry(EntryType::CACHE, [
            'type' => 'hit',
            'key' => 'cards:1',
        ], [
            'batch_id' => $batchId,
            'sequence' => 20,
        ]);

        $response = $this->getJson('/telescope/telescope-api/requests/'.$request->uuid)
            ->assertSuccessful()
            ->assertJsonPath('entry.id', $request->uuid)
            ->assertJsonCount(3, 'batch')
            ->assertJsonCount(3, 'timeline');

        $timeline = $response->json('timeline');

        $this->assertSame(['query', 'cache', 'request'], array_column($timeline, 'type'));
        $this->assertSame('select * from cards', $timeline[0]['summary']);
        $this->assertSame('12.5ms', $timeline[0]['duration']);
        $this->assertSame('hit cards:1', $timeline[1]['summary']);
        $this->assertSame('GET /v1/cards', $timeline[2]['summary']);
    }
}
