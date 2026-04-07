<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard\Infrastructure\Logging;

use App\Dashboard\Infrastructure\Logging\PerformanceLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;

final class PerformanceLoggerTest extends TestCase
{
    private TestLogger        $testLogger;
    private PerformanceLogger $logger;

    protected function setUp(): void
    {
        $this->testLogger = new TestLogger();
        $this->logger     = new PerformanceLogger($this->testLogger);
    }

    public function test_fast_query_logs_at_info_level(): void
    {
        $this->logger->logQuery('GetDashboard', 45.3);

        $this->assertTrue($this->testLogger->hasInfoThatContains('GetDashboard'));
        $this->assertFalse($this->testLogger->hasWarningRecords());
    }

    public function test_slow_query_logs_at_warning_level(): void
    {
        $this->logger->logQuery('GetDashboard', 350.0);

        $this->assertTrue($this->testLogger->hasWarningThatContains('SLOW QUERY'));
        $this->assertFalse($this->testLogger->hasInfoRecords());
    }

    public function test_slow_flag_is_true_when_over_threshold(): void
    {
        $this->logger->logQuery('GetDashboard', 250.0);

        $this->assertTrue($this->testLogger->records[0]['context']['slow']);
    }

    public function test_slow_flag_is_false_for_fast_queries(): void
    {
        $this->logger->logQuery('GetDashboard', 10.0);

        $this->assertFalse($this->testLogger->records[0]['context']['slow']);
    }

    public function test_duration_is_rounded_and_included_in_context(): void
    {
        $this->logger->logQuery('GetDashboard', 123.456);

        $this->assertSame(123.46, $this->testLogger->records[0]['context']['duration_ms']);
    }

    public function test_cache_event_logs_at_debug_level(): void
    {
        $this->logger->logCacheEvent('hit', 'dashboard_p1');

        $this->assertTrue($this->testLogger->hasDebugThatContains('HIT'));
        $this->assertTrue($this->testLogger->hasDebugThatContains('dashboard_p1'));
    }

    public function test_extra_context_is_merged_into_log(): void
    {
        $this->logger->logQuery('GetDashboard', 80.0, ['page' => 3, 'total' => 100000]);

        $ctx = $this->testLogger->records[0]['context'];
        $this->assertSame(3,      $ctx['page']);
        $this->assertSame(100000, $ctx['total']);
    }
}
