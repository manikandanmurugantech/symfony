<?php

declare(strict_types=1);

namespace App\Dashboard\Application\Query\GetDashboard;

interface DashboardReadModelRepositoryInterface
{
    public function findPaginated(GetDashboardQuery $query): DashboardResult;

    public function upsert(array $data): void;

    public function countTotal(): int;
}
