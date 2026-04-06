<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard\Infrastructure\Cache;

use App\Dashboard\Application\Query\GetDashboard\DashboardReadModel;
use App\Dashboard\Application\Query\GetDashboard\DashboardReadModelRepositoryInterface;
use App\Dashboard\Application\Query\GetDashboard\DashboardResult;
use App\Dashboard\Application\Query\GetDashboard\GetDashboardQuery;
use App\Dashboard\Infrastructure\Cache\CachedDashboardReadModelRepository;
use App\Dashboard\Infrastructure\Logging\PerformanceLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\NullLogger;

final class CachedDashboardReadModelRepositoryTest extends TestCase
{
    private DashboardReadModelRepositoryInterface&MockObject $inner;
    private CacheItemPoolInterface&MockObject                $cache;
    private CachedDashboardReadModelRepository               $repository;

    protected function setUp(): void
    {
        $this->inner      = $this->createMock(DashboardReadModelRepositoryInterface::class);
        $this->cache      = $this->createMock(CacheItemPoolInterface::class);
        $logger           = new PerformanceLogger(new NullLogger());

        $this->repository = new CachedDashboardReadModelRepository(
            inner:  $this->inner,
            cache:  $this->cache,
            logger: new NullLogger(),
            ttl:    60,
        );
    }

    public function test_returns_cached_result_on_cache_hit(): void
    {
        $query          = new GetDashboardQuery(page: 1, limit: 50);
        $cachedResult   = $this->makeDashboardResult();
        $cacheItem      = $this->createCacheItem(isHit: true, value: $cachedResult);

        $this->cache->method('getItem')->willReturn($cacheItem);

        // Inner repository must NOT be called on cache hit
        $this->inner->expects($this->never())->method('findPaginated');

        $result = $this->repository->findPaginated($query);

        $this->assertSame($cachedResult, $result);
    }

    public function test_calls_inner_repository_on_cache_miss(): void
    {
        $query       = new GetDashboardQuery(page: 1, limit: 50);
        $freshResult = $this->makeDashboardResult();
        $cacheItem   = $this->createCacheItem(isHit: false);

        $this->cache->method('getItem')->willReturn($cacheItem);
        $this->cache->method('save')->willReturn(true);

        $this->inner
            ->expects($this->once())
            ->method('findPaginated')
            ->with($query)
            ->willReturn($freshResult);

        $result = $this->repository->findPaginated($query);

        $this->assertSame($freshResult, $result);
    }

    public function test_saves_result_to_cache_after_miss(): void
    {
        $query     = new GetDashboardQuery();
        $cacheItem = $this->createCacheItem(isHit: false);

        $this->cache->method('getItem')->willReturn($cacheItem);
        $this->inner->method('findPaginated')->willReturn($this->makeDashboardResult());

        // Verify that the item is saved to cache
        $this->cache->expects($this->once())->method('save')->with($cacheItem);

        $this->repository->findPaginated($query);
    }

    public function test_different_queries_use_different_cache_keys(): void
    {
        $usedKeys = [];

        $this->cache
            ->method('getItem')
            ->willReturnCallback(function (string $key) use (&$usedKeys) {
                $usedKeys[] = $key;
                return $this->createCacheItem(isHit: false);
            });

        $this->cache->method('save')->willReturn(true);
        $this->inner->method('findPaginated')->willReturn($this->makeDashboardResult());

        $this->repository->findPaginated(new GetDashboardQuery(page: 1));
        $this->repository->findPaginated(new GetDashboardQuery(page: 2));
        $this->repository->findPaginated(new GetDashboardQuery(page: 1, country: 'IN'));

        $this->assertCount(3, array_unique($usedKeys));
    }

    public function test_upsert_delegates_to_inner_repository(): void
    {
        $data = ['id' => 'some-id', 'url' => '/test'];

        $this->inner
            ->expects($this->once())
            ->method('upsert')
            ->with($data);

        $this->repository->upsert($data);
    }

    public function test_count_total_delegates_to_inner_repository(): void
    {
        $this->inner->expects($this->once())->method('countTotal')->willReturn(123456);

        $count = $this->repository->countTotal();

        $this->assertSame(123456, $count);
    }

    // -------------------------------------------------------------------------
    private function createCacheItem(bool $isHit, mixed $value = null): CacheItemInterface&MockObject
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn($isHit);
        $item->method('get')->willReturn($value);
        $item->method('set')->willReturnSelf();
        $item->method('expiresAfter')->willReturnSelf();
        return $item;
    }

    private function makeDashboardResult(): DashboardResult
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
