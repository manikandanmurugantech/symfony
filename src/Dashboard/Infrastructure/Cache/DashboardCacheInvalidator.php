<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Cache;

use App\Dashboard\Infrastructure\Logging\PerformanceLogger;
use Psr\Cache\CacheItemPoolInterface;

final class DashboardCacheInvalidator
{
    private const KEY_PREFIX = 'dashboard_read_model';

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly PerformanceLogger      $performanceLogger,
    ) {}

    public function invalidateAll(): void
    {
        // PSR-6: delete by prefix pattern via tagged cache pool
        // With Symfony's TagAwareAdapter this would use tags; here we clear the pool namespace
        $this->cache->clear();
        $this->performanceLogger->logCacheEvent('invalidate_all', self::KEY_PREFIX . '_*');
    }
}
