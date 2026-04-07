<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Cache;

use App\Dashboard\Application\Query\GetDashboard\DashboardReadModelRepositoryInterface;
use App\Dashboard\Application\Query\GetDashboard\DashboardResult;
use App\Dashboard\Application\Query\GetDashboard\GetDashboardQuery;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Sits in front of the real read model repository and caches results in Redis.
 * Uses Symfony's TagAwareCacheInterface so we can invalidate all dashboard
 * pages at once with a single tag, instead of flushing the whole pool.
 */
final class CachedDashboardReadModelRepository implements DashboardReadModelRepositoryInterface
{
    private const TAG = 'dashboard';

    public function __construct(
        private readonly DashboardReadModelRepositoryInterface $inner,
        private readonly TagAwareCacheInterface                $cache,
        private readonly LoggerInterface                       $logger,
        private readonly int                                   $ttl = 300,
    ) {}

    public function findPaginated(GetDashboardQuery $query): DashboardResult
    {
        $key = $this->buildKey($query);

        return $this->cache->get($key, function (ItemInterface $item) use ($query, $key): DashboardResult {
            $item->expiresAfter($this->ttl);
            $item->tag([self::TAG]);

            $this->logger->debug('Dashboard cache miss — fetching from DB', ['key' => $key]);

            return $this->inner->findPaginated($query);
        });
    }

    public function invalidateAll(): void
    {
        $this->cache->invalidateTags([self::TAG]);
        $this->logger->debug('Dashboard cache invalidated', ['tag' => self::TAG]);
    }

    public function upsert(array $data): void
    {
        $this->inner->upsert($data);
    }

    public function countTotal(): int
    {
        return $this->inner->countTotal();
    }

    private function buildKey(GetDashboardQuery $query): string
    {
        return sprintf(
            'dashboard_p%d_l%d_%s_%s_url%s_sc%s_c%s',
            $query->page,
            $query->limit,
            $query->sortBy,
            $query->sortDir,
            md5($query->filterUrl ?? ''),
            $query->statusCode ?? 'all',
            $query->country    ?? 'all',
        );
    }
}
