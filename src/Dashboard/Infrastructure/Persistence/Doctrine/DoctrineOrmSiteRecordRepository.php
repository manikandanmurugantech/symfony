<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Persistence\Doctrine;

use App\Dashboard\Domain\Model\SiteRecord;
use App\Dashboard\Domain\Model\SiteRecordId;
use App\Dashboard\Domain\Repository\SiteRecordRepositoryInterface;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

/**
 * Write-side repository using raw DBAL for performance.
 * The write model (site_records) is the source of truth.
 */
final class DoctrineOrmSiteRecordRepository implements SiteRecordRepositoryInterface
{
    public function __construct(private readonly Connection $connection) {}

    public function save(SiteRecord $record): void
    {
        $this->connection->executeStatement(
            'INSERT INTO site_records
                (id, url, ip_address, user_agent, http_method, status_code, response_time_ms, country, occurred_at)
             VALUES
                (:id, :url, :ip_address, :user_agent, :http_method, :status_code, :response_time_ms, :country, :occurred_at)
             ON CONFLICT (id) DO UPDATE SET
                url              = EXCLUDED.url,
                status_code      = EXCLUDED.status_code,
                response_time_ms = EXCLUDED.response_time_ms',
            [
                'id'               => $record->id()->value(),
                'url'              => $record->url(),
                'ip_address'       => $record->ipAddress(),
                'user_agent'       => $record->userAgent(),
                'http_method'      => $record->httpMethod(),
                'status_code'      => $record->statusCode(),
                'response_time_ms' => $record->responseTimeMs(),
                'country'          => $record->country(),
                'occurred_at'      => $record->occurredAt()->format('Y-m-d H:i:s'),
            ]
        );
    }

    public function findById(SiteRecordId $id): ?SiteRecord
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM site_records WHERE id = :id',
            ['id' => $id->value()]
        );

        if (!$row) {
            return null;
        }

        return SiteRecord::create(
            SiteRecordId::fromString($row['id']),
            $row['url'],
            $row['ip_address'],
            $row['user_agent'],
            $row['http_method'],
            (int) $row['status_code'],
            (float) $row['response_time_ms'],
            $row['country'],
            new DateTimeImmutable($row['occurred_at']),
        );
    }
}
