<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Cache;

use App\Dashboard\Application\Query\GetDashboard\DashboardReadModelRepositoryInterface;
use App\Dashboard\Application\Query\GetDashboard\DashboardResult;
use App\Dashboard\Application\Query\GetDashboard\GetDashboardQuery;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Decorator pattern: wraps the real read model repository with Redis caching.
 * Cache key encodes all query parameters for fine-grained invalidation.
 */
final class CachedDashboardReadModelRepository implements DashboardReadModelRepositoryInterface
{
    private const CACHE_TAG = 'dashboard_read_model';

    public function __construct(
        private readonly DashboardReadModelRepositoryInterface $inner,
        private readonly CacheItemPoolInterface                $cache,
        private readonly LoggerInterface                       $logger,
        private readonly int                                   $ttl = 300,
    ) {}

    public function findPaginated(GetDashboardQuery $query): DashboardResult
    {
        $cacheKey = $this->buildCacheKey($query);
        $item     = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            $this->logger->debug('Dashboard cache HIT', ['key' => $cacheKey]);
            return $item->get();
        }

        $this->logger->debug('Dashboard cache MISS', ['key' => $cacheKey]);

        $result = $this->inner->findPaginated($query);

        $item->set($result);
        $item->expiresAfter($this->ttl);
        $this->cache->save($item);

        return $result;
    }

    public function upsert(array $data): void
    {
        $this->inner->upsert($data);
    }

    public function countTotal(): int
    {
        return $this->inner->countTotal();
    }

    private function buildCacheKey(GetDashboardQuery $query): string
    {
        return sprintf(
            '%s_p%d_l%d_s%s_%s_url%s_sc%s_c%s',
            self::CACHE_TAG,
            $query->page,
            $query->limit,
            $query->sortBy,
            $query->sortDir,
            md5($query->filterUrl ?? ''),
            $query->statusCode ?? 'all',
            $query->country ?? 'all',
        );
    }
}
