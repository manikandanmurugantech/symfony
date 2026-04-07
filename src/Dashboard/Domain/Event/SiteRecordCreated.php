<?php

declare(strict_types=1);

namespace App\Dashboard\Domain\Event;

use DateTimeImmutable;

final class SiteRecordCreated
{
    public function __construct(
        public readonly string            $siteRecordId,
        public readonly string            $url,
        public readonly string            $ipAddress,
        public readonly string            $httpMethod,
        public readonly int               $statusCode,
        public readonly float             $responseTimeMs,
        public readonly string            $country,
        public readonly DateTimeImmutable $occurredAt,
    ) {}
}
