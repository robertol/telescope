<?php

namespace Laravel\Telescope\Tests;

use Laravel\Telescope\EndpointMatcher;
use PHPUnit\Framework\TestCase;

class EndpointMatcherTest extends TestCase
{
    public function test_it_matches_exact_paths()
    {
        $this->assertTrue(EndpointMatcher::matches('/api/users', 'GET', 'api/users'));
        $this->assertFalse(EndpointMatcher::matches('/api/posts', 'GET', 'api/users'));
    }

    public function test_it_matches_wildcard_paths()
    {
        $this->assertTrue(EndpointMatcher::matches('/api/users/1', 'GET', 'api/users/*'));
        $this->assertFalse(EndpointMatcher::matches('/api/posts/1', 'GET', 'api/users/*'));
    }

    public function test_it_matches_method_prefixed_patterns()
    {
        $this->assertTrue(EndpointMatcher::matches('/api/users', 'POST', 'POST:api/users'));
        $this->assertFalse(EndpointMatcher::matches('/api/users', 'GET', 'POST:api/users'));
        $this->assertTrue(EndpointMatcher::matches('/api/users', 'GET', '*:api/users'));
    }

    public function test_it_ignores_query_strings_on_stored_uris()
    {
        $this->assertTrue(EndpointMatcher::matches('/v1/admin/notifications?per_page=8', 'GET', 'GET:/v1/admin/notifications'));
        $this->assertTrue(EndpointMatcher::matches('/v1/admin/notifications?per_page=8', 'GET', '/v1/admin/notifications'));
        $this->assertFalse(EndpointMatcher::matches('/v1/admin/notifications?per_page=8', 'GET', 'GET:/v1/admin/other'));
    }

    public function test_it_matches_leading_wildcard_without_requiring_a_prefix_segment()
    {
        $this->assertTrue(EndpointMatcher::matches('/api/webhooks/foo', 'POST', '*/api/webhooks/*'));
        $this->assertTrue(EndpointMatcher::matches('/v1/api/webhooks/x', 'POST', '*/api/webhooks/*'));
        $this->assertFalse(EndpointMatcher::matches('/api/other', 'POST', '*/api/webhooks/*'));
        $this->assertTrue(EndpointMatcher::matches('/api/webhooks/foo', 'GET', 'api/webhooks/*'));
        $this->assertTrue(EndpointMatcher::matches('/api/webhooks/foo', 'GET', 'GET:/api/webhooks/*'));
    }
}
