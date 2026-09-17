<?php

namespace Laravel\Telescope\Tests\Http;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Laravel\Telescope\Database\Factories\EntryModelFactory;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Tests\FeatureTestCase;
use Orchestra\Testbench\Http\Middleware\VerifyCsrfToken;

class MonitoredEndpointTest extends FeatureTestCase
{
    /** {@inheritdoc} */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([Authorize::class, VerifyCsrfToken::class, ValidateCsrfToken::class, PreventRequestForgery::class]);
    }

    public function test_it_lists_monitored_endpoints()
    {
        DB::table('telescope_monitored_endpoints')->insert([
            ['endpoint' => '*/api/webhooks/*'],
        ]);

        $this->get('/telescope/telescope-api/monitored-endpoints')
            ->assertSuccessful()
            ->assertJson([
                'endpoints' => ['*/api/webhooks/*'],
            ]);
    }

    public function test_it_filters_requests_by_leading_wildcard_endpoint_pattern()
    {
        $webhook = EntryModelFactory::new()->create([
            'type' => EntryType::REQUEST,
            'content' => [
                'method' => 'POST',
                'uri' => '/api/webhooks/stripe',
                'response_status' => 200,
            ],
        ]);

        EntryModelFactory::new()->create([
            'type' => EntryType::REQUEST,
            'content' => [
                'method' => 'GET',
                'uri' => '/v1/admin/cards',
                'response_status' => 200,
            ],
        ]);

        $this->post('/telescope/telescope-api/requests?endpoint='.urlencode('*/api/webhooks/*'))
            ->assertSuccessful()
            ->assertJsonPath('entries.0.id', $webhook->uuid)
            ->assertJsonCount(1, 'entries');
    }

    public function test_it_loads_package_migrations_so_consumers_do_not_copy_schema()
    {
        $expected = realpath(dirname(__DIR__, 2).'/database/migrations');

        $this->assertNotFalse($expected);
        $this->assertContains(
            $expected,
            array_map('realpath', app('migrator')->paths())
        );
    }
}
