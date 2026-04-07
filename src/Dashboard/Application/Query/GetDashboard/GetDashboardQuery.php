<?php

declare(strict_types=1);

namespace App\Dashboard\Application\Query\GetDashboard;

final class GetDashboardQuery
{
    public function __construct(
        public readonly int    $page        = 1,
        public readonly int    $limit       = 50,
        public readonly string $sortBy      = 'occurred_at',
        public readonly string $sortDir     = 'DESC',
        public readonly ?string $filterUrl  = null,
        public readonly ?int    $statusCode = null,
        public readonly ?string $country    = null,
    ) {}
}
