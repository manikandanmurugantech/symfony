<?php

declare(strict_types=1);

namespace App\Dashboard\Application\Query\GetDashboard;

final class DashboardResult
{
    /**
     * @param DashboardReadModel[] $records
     */
    public function __construct(
        public readonly array $records,
        public readonly int   $total,
        public readonly int   $page,
        public readonly int   $limit,
        public readonly int   $totalPages,
        public readonly float $queryTimeMs,
    ) {}
}
