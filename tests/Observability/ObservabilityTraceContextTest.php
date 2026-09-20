<?php

namespace Laravel\Telescope\Tests\Observability;

use Laravel\Telescope\EntryType;
use Laravel\Telescope\Observability\ObservabilityTraceContext;
use Laravel\Telescope\Tests\FeatureTestCase;

class ObservabilityTraceContextTest extends FeatureTestCase
{
    public function test_child_spans_inherit_the_request_trace_and_parent_span(): void
    {
        $context = new ObservabilityTraceContext;
        $context->start();

        $request = $context->spanFor(EntryType::REQUEST);
        $query = $context->spanFor(EntryType::QUERY);
        $http = $context->spanFor(EntryType::CLIENT_REQUEST);

        $this->assertNotEmpty($request['trace_id']);
        $this->assertSame($request['trace_id'], $query['trace_id']);
        $this->assertSame($request['trace_id'], $http['trace_id']);
        $this->assertNull($request['parent_span_id']);
        $this->assertSame($request['span_id'], $query['parent_span_id']);
        $this->assertSame($request['span_id'], $http['parent_span_id']);
        $this->assertNotSame($query['span_id'], $http['span_id']);
    }

    public function test_reset_isolates_trace_context_between_requests(): void
    {
        $context = new ObservabilityTraceContext;

        $context->start();
        $first = $context->spanFor(EntryType::REQUEST);

        $context->reset();
        $context->start();
        $second = $context->spanFor(EntryType::REQUEST);

        $this->assertNotSame($first['trace_id'], $second['trace_id']);
        $this->assertNotSame($first['span_id'], $second['span_id']);
    }

    public function test_queries_recorded_before_the_request_entry_still_parent_to_the_root_span(): void
    {
        $context = new ObservabilityTraceContext;
        $context->start();

        $query = $context->spanFor(EntryType::QUERY);
        $request = $context->spanFor(EntryType::REQUEST);

        $this->assertSame($request['trace_id'], $query['trace_id']);
        $this->assertSame($request['span_id'], $query['parent_span_id']);
    }
}
