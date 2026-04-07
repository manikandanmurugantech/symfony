<?php

declare(strict_types=1);

namespace App\Dashboard\Application\EventHandler;

use App\Dashboard\Application\Query\GetDashboard\DashboardReadModelRepositoryInterface;
use App\Dashboard\Domain\Event\SiteRecordCreated;
use App\Dashboard\Infrastructure\Cache\DashboardCacheInvalidator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Async event handler: consumes SiteRecordCreated events from the queue,
 * updates the denormalized read model, and invalidates the dashboard cache.
 *
 * This is the "projection" step in Event-Driven Architecture.
 */
#[AsMessageHandler(bus: 'messenger.bus.events')]
final class UpdateDashboardProjectionHandler
{
    public function __construct(
        private readonly DashboardReadModelRepositoryInterface $readModelRepository,
        private readonly DashboardCacheInvalidator             $cacheInvalidator,
        private readonly LoggerInterface                       $logger,
    ) {}

    public function __invoke(SiteRecordCreated $event): void
    {
        $this->logger->info('Projecting SiteRecordCreated to read model', [
            'id'          => $event->siteRecordId,
            'url'         => $event->url,
            'status_code' => $event->statusCode,
        ]);

        $this->readModelRepository->upsert([
            'id'               => $event->siteRecordId,
            'url'              => $event->url,
            'ip_address'       => $event->ipAddress,
            'http_method'      => $event->httpMethod,
            'status_code'      => $event->statusCode,
            'response_time_ms' => $event->responseTimeMs,
            'country'          => $event->country,
            'occurred_at'      => $event->occurredAt->format('Y-m-d H:i:s'),
        ]);

        // Invalidate cached dashboard pages so readers see fresh data
        $this->cacheInvalidator->invalidateAll();

        $this->logger->info('Dashboard projection updated', [
            'id' => $event->siteRecordId,
        ]);
    }
}
