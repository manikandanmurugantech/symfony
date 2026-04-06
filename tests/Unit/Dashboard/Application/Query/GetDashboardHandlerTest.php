<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard\Application\Query;

use App\Dashboard\Application\Query\GetDashboard\DashboardReadModel;
use App\Dashboard\Application\Query\GetDashboard\DashboardReadModelRepositoryInterface;
use App\Dashboard\Application\Query\GetDashboard\DashboardResult;
use App\Dashboard\Application\Query\GetDashboard\GetDashboardHandler;
use App\Dashboard\Application\Query\GetDashboard\GetDashboardQuery;
use App\Dashboard\Infrastructure\Logging\PerformanceLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class GetDashboardHandlerTest extends TestCase
{
    private DashboardReadModelRepositoryInterface&MockObject $repository;
    private GetDashboardHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(DashboardReadModelRepositoryInterface::class);
        $performanceLogger = new PerformanceLogger(new NullLogger());

        $this->handler = new GetDashboardHandler(
            $this->repository,
            $performanceLogger,
        );
    }

    public function test_returns_dashboard_result_from_repository(): void
    {
        $query          = new GetDashboardQuery(page: 1, limit: 25);
        $expectedResult = $this->makeDashboardResult(records: 3, total: 100000);

        $this->repository
            ->expects($this->once())
            ->method('findPaginated')
            ->with($query)
            ->willReturn($expectedResult);

        $result = ($this->handler)($query);

        $this->assertSame($expectedResult, $result);
        $this->assertCount(3, $result->records);
        $this->assertSame(100000, $result->total);
    }

    public function test_passes_query_to_repository_unchanged(): void
    {
        $query = new GetDashboardQuery(
            page:       3,
            limit:      100,
            sortBy:     'status_code',
            sortDir:    'ASC',
            filterUrl:  '/api',
            statusCode: 200,
            country:    'IN',
        );

        $this->repository
            ->expects($this->once())
            ->method('findPaginated')
            ->with($this->callback(fn ($q) =>
                $q->page       === 3    &&
                $q->limit      === 100  &&
                $q->sortBy     === 'status_code' &&
                $q->country    === 'IN'
            ))
            ->willReturn($this->makeDashboardResult());

        ($this->handler)($query);
    }

    public function test_returns_empty_result_when_no_records(): void
    {
        $this->repository
            ->method('findPaginated')
            ->willReturn($this->makeDashboardResult(records: 0, total: 0));

        $result = ($this->handler)(new GetDashboardQuery());

        $this->assertCount(0, $result->records);
        $this->assertSame(0, $result->total);
    }

    public function test_handler_calls_repository_exactly_once(): void
    {
        $this->repository
            ->expects($this->exactly(1))
            ->method('findPaginated')
            ->willReturn($this->makeDashboardResult());

        ($this->handler)(new GetDashboardQuery());
    }

    // -------------------------------------------------------------------------
    private function makeDashboardResult(int $records = 2, int $total = 100000): DashboardResult
    {
        $rows = [];
        for ($i = 0; $i < $records; $i++) {
            $rows[] = new DashboardReadModel(
                id:             sprintf('id-%d', $i),
                url:            '/page/' . $i,
                ipAddress:      '10.0.0.' . $i,
                httpMethod:     'GET',
                statusCode:     200,
                responseTimeMs: 123.4,
                country:        'IN',
                occurredAt:     '2024-01-01 00:00:00',
            );
        }

        return new DashboardResult(
            records:     $rows,
            total:       $total,
            page:        1,
            limit:       50,
            totalPages:  (int) ceil($total / 50),
            queryTimeMs: 0.5,
        );
    }
}
