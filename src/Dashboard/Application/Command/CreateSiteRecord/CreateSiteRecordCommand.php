<?php

declare(strict_types=1);

namespace App\Dashboard\Application\Command\CreateSiteRecord;

final class CreateSiteRecordCommand
{
    public function __construct(
        public readonly string $url,
        public readonly string $ipAddress,
        public readonly string $userAgent,
        public readonly string $httpMethod,
        public readonly int    $statusCode,
        public readonly float  $responseTimeMs,
        public readonly string $country,
    ) {}
}
