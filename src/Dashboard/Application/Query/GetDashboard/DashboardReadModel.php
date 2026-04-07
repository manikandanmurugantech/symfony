<?php

declare(strict_types=1);

namespace App\Dashboard\Application\Query\GetDashboard;

final class DashboardReadModel
{
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly string $ipAddress,
        public readonly string $httpMethod,
        public readonly int    $statusCode,
        public readonly float  $responseTimeMs,
        public readonly string $country,
        public readonly string $occurredAt,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id:             $row['id'],
            url:            $row['url'],
            ipAddress:      $row['ip_address'],
            httpMethod:     $row['http_method'],
            statusCode:     (int)   $row['status_code'],
            responseTimeMs: (float) $row['response_time_ms'],
            country:        $row['country'],
            occurredAt:     $row['occurred_at'],
        );
    }
}
