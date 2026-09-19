<?php

namespace Laravel\Telescope\Tests\Http;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Tests\Console\CreatesTelescopeEntries;
use Laravel\Telescope\Tests\FeatureTestCase;
use Orchestra\Testbench\Http\Middleware\VerifyCsrfToken;

class OutgoingRequestsTest extends FeatureTestCase
{
    use CreatesTelescopeEntries;

    /** {@inheritdoc} */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([Authorize::class, VerifyCsrfToken::class, ValidateCsrfToken::class, PreventRequestForgery::class]);
    }

    public function test_outgoing_hosts_only_include_tagged_hosts(): void
    {
        $one = $this->createEntry(EntryType::CLIENT_REQUEST, [
            'method' => 'GET',
            'uri' => 'https://flightwatch.io/api/a',
            'response_status' => 200,
            'duration' => 20,
            'source' => 'request',
        ]);

        $two = $this->createEntry(EntryType::CLIENT_REQUEST, [
            'method' => 'GET',
            'uri' => 'https://other.example/api/b',
            'response_status' => 500,
            'duration' => 40,
            'source' => 'job',
        ]);

        DB::table('telescope_entries_tags')->insert([
            ['entry_uuid' => $one->uuid, 'tag' => 'flightwatch.io'],
            ['entry_uuid' => $two->uuid, 'tag' => 'other.example'],
        ]);

        $this->getJson('/telescope/telescope-api/outgoing-requests/hosts?hours=336')
            ->assertSuccessful()
            ->assertJsonCount(2, 'hosts');

        $this->getJson('/telescope/telescope-api/outgoing-requests?host=flightwatch.io&hours=336')
            ->assertSuccessful()
            ->assertJsonPath('requests.total', 1)
            ->assertJsonPath('requests.status.2xx', 1)
            ->assertJsonPath('requests.status.5xx', 0)
            ->assertJsonPath('entries.0.content.source', 'request')
            ->assertJsonPath('entries.0.content.uri', 'https://flightwatch.io/api/a');

        $bucket = collect($this->getJson('/telescope/telescope-api/outgoing-requests?host=flightwatch.io&hours=336')->json('requests.buckets'))
            ->first(fn ($row) => ($row['total'] ?? 0) > 0);

        $this->assertNotNull($bucket);
        $this->assertSame(1, $bucket['123xx']);
        $this->assertSame(0, $bucket['5xx']);
        $this->assertEquals(20, $bucket['avg']);
    }
}
