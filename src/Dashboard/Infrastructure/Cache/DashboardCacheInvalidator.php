<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Cache;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class DashboardCacheInvalidator
{
    public function __construct(
        private readonly TagAwareCacheInterface $cache,
        private readonly LoggerInterface        $logger,
    ) {}

    public function invalidateAll(): void
    {
        $this->cache->invalidateTags(['dashboard']);
        $this->logger->debug('[CACHE] invalidated tag: dashboard');
    }
}
