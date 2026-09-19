<?php

namespace Laravel\Telescope\Tests\Http;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Tests\FeatureTestCase;
use Laravel\Telescope\Watchers\RequestWatcher;
use Orchestra\Testbench\Http\Middleware\VerifyCsrfToken;

class SettingsTest extends FeatureTestCase
{
    /** {@inheritdoc} */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([Authorize::class, VerifyCsrfToken::class, ValidateCsrfToken::class, PreventRequestForgery::class]);
    }

    public function test_settings_endpoint_reports_recording_and_watchers(): void
    {
        $this->getJson('/telescope/telescope-api/settings')
            ->assertSuccessful()
            ->assertJsonPath('recording', true)
            ->assertJsonFragment([
                'class' => RequestWatcher::class,
            ]);
    }
}
