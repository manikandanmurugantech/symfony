<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard\Infrastructure\Cache;

use App\Dashboard\Application\Query\GetDashboard\DashboardReadModelRepositoryInterface;
use App\Dashboard\Application\Query\GetDashboard\DashboardResult;
use App\Dashboard\Application\Query\GetDashboard\GetDashboardQuery;
use App\Dashboard\Infrastructure\Cache\CachedDashboardReadModelRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class CachedDashboardReadModelRepositoryTest extends TestCase
{
    private DashboardReadModelRepositoryInterface&MockObject $inner;
    private TagAwareCacheInterface&MockObject                $cache;
    private CachedDashboardReadModelRepository               $repo;

    protected function setUp(): void
    {
        $this->inner = $this->createMock(DashboardReadModelRepositoryInterface::class);
        $this->cache = $this->createMock(TagAwareCacheInterface::class);

        $this->repo = new CachedDashboardReadModelRepository(
            inner:  $this->inner,
            cache:  $this->cache,
            logger: new NullLogger(),
            ttl:    60,
        );
    }

    public function test_find_paginated_delegates_to_cache(): void
    {
        $query  = new GetDashboardQuery(page: 1, limit: 50);
        $result = $this->makeResult();

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturn($result);

        $actual = $this->repo->findPaginated($query);

        $this->assertSame($result, $actual);
    }

    public function test_different_queries_produce_different_cache_keys(): void
    {
        $keys = [];

        $this->cache
            ->method('get')
            ->willReturnCallback(function (string $key) use (&$keys) {
                $keys[] = $key;
                return $this->makeResult();
            });

        $this->repo->findPaginated(new GetDashboardQuery(page: 1));
        $this->repo->findPaginated(new GetDashboardQuery(page: 2));
        $this->repo->findPaginated(new GetDashboardQuery(page: 1, country: 'IN'));

        $this->assertCount(3, array_unique($keys), 'Each unique query must generate a unique cache key.');
    }

    public function test_invalidate_all_calls_cache_invalidate_tags(): void
    {
        $this->cache
            ->expects($this->once())
            ->method('invalidateTags')
            ->with(['dashboard']);

        $this->repo->invalidateAll();
    }

    public function test_upsert_delegates_to_inner_repository(): void
    {
        $row = ['id' => 'abc-123', 'url' => '/api/test'];

        $this->inner
            ->expects($this->once())
            ->method('upsert')
            ->with($row);

        $this->repo->upsert($row);
    }

    public function test_count_total_delegates_to_inner_repository(): void
    {
        $this->inner->method('countTotal')->willReturn(100000);

        $this->assertSame(100000, $this->repo->countTotal());
    }

    // -------------------------------------------------------------------------

    private function makeResult(): DashboardResult
    {
        return new DashboardResult(
            records:     [],
            total:       100000,
            page:        1,
            limit:       50,
            totalPages:  2000,
            queryTimeMs: 0.3,
        );
    }
}
