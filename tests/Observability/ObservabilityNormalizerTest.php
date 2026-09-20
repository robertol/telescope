<?php

namespace Laravel\Telescope\Tests\Observability;

use Laravel\Telescope\EntryType;
use Laravel\Telescope\Observability\ObservabilityNormalizer;
use Laravel\Telescope\Tests\FeatureTestCase;

class ObservabilityNormalizerTest extends FeatureTestCase
{
    use MakesIncomingEntries;

    public function test_it_extracts_request_columns_and_keeps_payloads_in_content(): void
    {
        $row = (new ObservabilityNormalizer)->normalize($this->incomingRequest());

        $this->assertSame('observability_requests', $row['table']);
        $this->assertSame('GET', $row['row']['method']);
        $this->assertSame('cards.index', $row['row']['route']);
        $this->assertSame('/v1/cards', $row['row']['uri']);
        $this->assertSame(200, $row['row']['status_code']);
        $this->assertSame(42, $row['row']['duration_ms']);
        $this->assertEqualsWithDelta(8.5, $row['row']['memory_peak'], 0.01);
        $this->assertSame(9, $row['row']['user_id']);
        $this->assertSame('127.0.0.1', $row['row']['ip']);
        $this->assertSame('self-testing', $row['row']['environment']);
        $this->assertSame(gethostname(), $row['row']['hostname']);
        $this->assertArrayNotHasKey('headers', $row['row']);
        $this->assertSame('********', $row['row']['content']['headers']['authorization']);
        $this->assertSame('********', $row['row']['content']['payload']['password']);
        $this->assertArrayNotHasKey('token', $this->queryString($row['row']['uri']));
    }

    public function test_it_extracts_query_columns_using_parameterized_sql(): void
    {
        $row = (new ObservabilityNormalizer)->normalize($this->incomingQuery());

        $this->assertSame('observability_queries', $row['table']);
        $this->assertSame('pgsql', $row['row']['connection']);
        $this->assertSame(md5('select * from users where id = ?'), $row['row']['query_hash']);
        $this->assertSame(12, $row['row']['duration_ms']);
        $this->assertStringNotContainsString('10', $row['row']['sql']);
    }

    public function test_it_extracts_exception_columns_and_keeps_trace_in_content(): void
    {
        $row = (new ObservabilityNormalizer)->normalize($this->incomingException());

        $this->assertSame('observability_exceptions', $row['table']);
        $this->assertSame(\RuntimeException::class, $row['row']['exception_class']);
        $this->assertSame('Boom', $row['row']['message']);
        $this->assertSame('/app/Foo.php', $row['row']['file']);
        $this->assertSame(12, $row['row']['line']);
        $this->assertNotEmpty($row['row']['exception_hash']);
        $this->assertSame([['file' => '/app/Foo.php', 'line' => 12]], $row['row']['content']['trace']);
    }

    public function test_it_extracts_job_columns_and_redacts_card_data(): void
    {
        $row = (new ObservabilityNormalizer)->normalize($this->incomingJob());

        $this->assertSame('observability_jobs', $row['table']);
        $this->assertSame('default', $row['row']['queue']);
        $this->assertSame('redis', $row['row']['connection']);
        $this->assertSame('App\\Jobs\\ChargeCard', $row['row']['job_class']);
        $this->assertSame('processed', $row['row']['status']);
        $this->assertSame(3, $row['row']['attempts']);
        $this->assertNull($row['row']['duration_ms']);
        $this->assertSame('********', $row['row']['content']['data']['card_number']);
    }

    public function test_it_extracts_http_client_host_and_path_without_query_string(): void
    {
        $row = (new ObservabilityNormalizer)->normalize($this->incomingClientRequest());

        $this->assertSame('observability_http_client', $row['table']);
        $this->assertSame('POST', $row['row']['method']);
        $this->assertSame('api.starkbank.com', $row['row']['host']);
        $this->assertSame('/v2/invoice', $row['row']['path']);
        $this->assertSame(201, $row['row']['status_code']);
        $this->assertSame(120, $row['row']['duration_ms']);
        $this->assertStringNotContainsString('api_key', $row['row']['path']);
        $this->assertSame('********', $row['row']['content']['headers']['authorization']);
    }

    public function test_it_hashes_cache_keys_and_does_not_index_the_raw_key(): void
    {
        $row = (new ObservabilityNormalizer)->normalize($this->incomingCache());

        $this->assertSame('observability_cache', $row['table']);
        $this->assertSame('hit', $row['row']['operation']);
        $this->assertTrue($row['row']['hit']);
        $this->assertNotSame('user:42:profile', $row['row']['key_hash']);
        $this->assertSame(64, strlen($row['row']['key_hash']));
        $this->assertArrayNotHasKey('key', $row['row']);
        $this->assertSame('********', $row['row']['content']['value']['token']);
    }

    public function test_it_extracts_log_level_without_indexing_the_message_column_separately(): void
    {
        $row = (new ObservabilityNormalizer)->normalize($this->incomingLog());

        $this->assertSame('observability_logs', $row['table']);
        $this->assertSame('error', $row['row']['level']);
        $this->assertSame('Payment failed', $row['row']['message']);
        $this->assertSame('********', $row['row']['content']['context']['cvv']);
        $this->assertSame(1, $row['row']['content']['context']['order']);
    }

    public function test_it_ignores_types_without_a_specialized_table(): void
    {
        $this->assertNull(
            (new ObservabilityNormalizer)->normalize($this->incoming(EntryType::MAIL, ['html' => '<p>Hi</p>']))
        );
    }

    /**
     * @return array<string, string>
     */
    protected function queryString(string $uri): array
    {
        $query = parse_url($uri, PHP_URL_QUERY);

        if (! is_string($query) || $query === '') {
            return [];
        }

        parse_str($query, $params);

        return $params;
    }
}
