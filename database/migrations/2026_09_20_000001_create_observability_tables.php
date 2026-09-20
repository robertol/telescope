<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('telescope.storage.database.connection');
    }

    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());

        if (! $schema->hasTable('observability_requests')) {
            $schema->create('observability_requests', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('batch_id')->nullable()->index();
                $table->uuid('trace_id')->nullable();
                $table->uuid('span_id')->nullable();
                $table->uuid('parent_span_id')->nullable();
                $table->string('environment', 50);
                $table->string('hostname')->nullable();
                $table->string('method', 16)->nullable();
                $table->string('route', 512)->nullable();
                $table->string('uri', 2048)->nullable();
                $table->unsignedSmallInteger('status_code')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->float('memory_peak')->nullable();
                $table->string('user_id', 64)->nullable();
                $table->string('ip', 45)->nullable();
                $table->json('content')->nullable();
                $table->timestamp('created_at')->nullable();

                // List recent requests / dashboard window.
                $table->index(['environment', 'created_at'], 'obs_requests_env_created_index');
                // Requests of a route.
                $table->index(['environment', 'route', 'created_at'], 'obs_requests_env_route_created_index');
                // Events of a trace.
                $table->index('trace_id', 'obs_requests_trace_id_index');
            });

            $this->createPartialIndex(
                'obs_requests_errors_index',
                'observability_requests (environment, created_at) WHERE status_code >= 500'
            );
            $this->createPartialIndex(
                'obs_requests_slow_index',
                'observability_requests (environment, created_at) WHERE duration_ms >= 1000'
            );
        }

        if (! $schema->hasTable('observability_queries')) {
            $schema->create('observability_queries', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('batch_id')->nullable()->index();
                $table->uuid('trace_id')->nullable();
                $table->uuid('span_id')->nullable();
                $table->uuid('parent_span_id')->nullable();
                $table->string('environment', 50);
                $table->string('hostname')->nullable();
                $table->string('connection')->nullable();
                $table->string('query_hash', 64);
                $table->text('sql');
                $table->unsignedInteger('duration_ms')->nullable();
                $table->json('content')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['environment', 'created_at'], 'obs_queries_env_created_index');
                $table->index(['environment', 'query_hash', 'created_at'], 'obs_queries_env_hash_created_index');
                $table->index('trace_id', 'obs_queries_trace_id_index');
            });
        }

        if (! $schema->hasTable('observability_exceptions')) {
            $schema->create('observability_exceptions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('batch_id')->nullable()->index();
                $table->uuid('trace_id')->nullable();
                $table->uuid('span_id')->nullable();
                $table->uuid('parent_span_id')->nullable();
                $table->string('environment', 50);
                $table->string('hostname')->nullable();
                $table->string('exception_class');
                $table->text('message');
                $table->string('file', 2048)->nullable();
                $table->unsignedInteger('line')->nullable();
                $table->string('exception_hash', 64);
                $table->json('content')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['environment', 'created_at'], 'obs_exceptions_env_created_index');
                $table->index(['environment', 'exception_hash', 'created_at'], 'obs_exceptions_env_hash_created_index');
                $table->index('trace_id', 'obs_exceptions_trace_id_index');
            });
        }

        if (! $schema->hasTable('observability_jobs')) {
            $schema->create('observability_jobs', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('batch_id')->nullable()->index();
                $table->uuid('trace_id')->nullable();
                $table->uuid('span_id')->nullable();
                $table->uuid('parent_span_id')->nullable();
                $table->string('environment', 50);
                $table->string('hostname')->nullable();
                $table->string('queue')->nullable();
                $table->string('connection')->nullable();
                $table->string('job_class')->nullable();
                $table->string('status', 32)->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->unsignedInteger('attempts')->nullable();
                $table->json('content')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['environment', 'created_at'], 'obs_jobs_env_created_index');
                $table->index(['environment', 'job_class', 'created_at'], 'obs_jobs_env_class_created_index');
                $table->index('trace_id', 'obs_jobs_trace_id_index');
            });
        }

        if (! $schema->hasTable('observability_http_client')) {
            $schema->create('observability_http_client', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('batch_id')->nullable()->index();
                $table->uuid('trace_id')->nullable();
                $table->uuid('span_id')->nullable();
                $table->uuid('parent_span_id')->nullable();
                $table->string('environment', 50);
                $table->string('hostname')->nullable();
                $table->string('method', 16)->nullable();
                $table->string('host')->nullable();
                $table->string('path', 2048)->nullable();
                $table->unsignedSmallInteger('status_code')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->json('content')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['environment', 'created_at'], 'obs_http_env_created_index');
                $table->index(['environment', 'host', 'created_at'], 'obs_http_env_host_created_index');
                $table->index('trace_id', 'obs_http_trace_id_index');
            });
        }

        if (! $schema->hasTable('observability_cache')) {
            $schema->create('observability_cache', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('batch_id')->nullable()->index();
                $table->uuid('trace_id')->nullable();
                $table->uuid('span_id')->nullable();
                $table->uuid('parent_span_id')->nullable();
                $table->string('environment', 50);
                $table->string('hostname')->nullable();
                $table->string('operation', 16)->nullable();
                $table->string('key_hash', 64);
                $table->string('store')->nullable();
                $table->boolean('hit')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->json('content')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['environment', 'created_at'], 'obs_cache_env_created_index');
                $table->index('trace_id', 'obs_cache_trace_id_index');
            });
        }

        if (! $schema->hasTable('observability_logs')) {
            $schema->create('observability_logs', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('batch_id')->nullable()->index();
                $table->uuid('trace_id')->nullable();
                $table->uuid('span_id')->nullable();
                $table->uuid('parent_span_id')->nullable();
                $table->string('environment', 50);
                $table->string('hostname')->nullable();
                $table->string('level', 32)->nullable();
                $table->text('message');
                $table->json('content')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['environment', 'level', 'created_at'], 'obs_logs_env_level_created_index');
                $table->index('trace_id', 'obs_logs_trace_id_index');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection($this->getConnection());

        $schema->dropIfExists('observability_logs');
        $schema->dropIfExists('observability_cache');
        $schema->dropIfExists('observability_http_client');
        $schema->dropIfExists('observability_jobs');
        $schema->dropIfExists('observability_exceptions');
        $schema->dropIfExists('observability_queries');
        $schema->dropIfExists('observability_requests');
    }

    protected function createPartialIndex(string $name, string $definition): void
    {
        $connection = Schema::connection($this->getConnection())->getConnection();

        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        $connection->statement("CREATE INDEX IF NOT EXISTS {$name} ON {$definition}");
    }
};
