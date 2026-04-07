<?php

declare(strict_types=1);

namespace App\Dashboard\Application\Query\GetDashboard;

use App\Dashboard\Infrastructure\Logging\PerformanceLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetDashboardHandler
{
    public function __construct(
        private readonly DashboardReadModelRepositoryInterface $readModelRepository,
        private readonly PerformanceLogger                     $performanceLogger,
    ) {}

    public function __invoke(GetDashboardQuery $query): DashboardResult
    {
        $start = microtime(true);

        $result = $this->readModelRepository->findPaginated($query);

        $elapsed = (microtime(true) - $start) * 1000;

        $this->performanceLogger->logQuery(
            queryName: 'GetDashboard',
            durationMs: $elapsed,
            context: [
                'page'        => $query->page,
                'limit'       => $query->limit,
                'total'       => $result->total,
                'cache_hit'   => $result->queryTimeMs < 1.0,
            ]
        );

        return $result;
    }
}
