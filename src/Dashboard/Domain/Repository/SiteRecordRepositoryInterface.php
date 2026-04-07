<?php

declare(strict_types=1);

namespace App\Dashboard\Domain\Repository;

use App\Dashboard\Domain\Model\SiteRecord;
use App\Dashboard\Domain\Model\SiteRecordId;

interface SiteRecordRepositoryInterface
{
    public function save(SiteRecord $record): void;

    public function findById(SiteRecordId $id): ?SiteRecord;
}
