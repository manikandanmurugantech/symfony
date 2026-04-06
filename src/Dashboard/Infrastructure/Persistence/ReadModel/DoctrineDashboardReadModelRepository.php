<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Persistence\ReadModel;

use App\Dashboard\Application\Query\GetDashboard\DashboardReadModel;
use App\Dashboard\Application\Query\GetDashboard\DashboardReadModelRepositoryInterface;
use App\Dashboard\Application\Query\GetDashboard\DashboardResult;
use App\Dashboard\Application\Query\GetDashboard\GetDashboardQuery;
use Doctrine\DBAL\Connection;

/**
 * Optimized read model using raw SQL against the denormalized dashboard_read_model table.
 * Uses DBAL directly (no ORM overhead) for maximum read performance.
 * Indexes on: url, status_code, country, occurred_at.
 */
final class DoctrineDashboardReadModelRepository implements DashboardReadModelRepositoryInterface
{
    private const ALLOWED_SORT_COLUMNS = [
        'occurred_at', 'url', 'status_code', 'response_time_ms', 'country',
    ];

    public function __construct(private readonly Connection $connection) {}

    public function findPaginated(GetDashboardQuery $query): DashboardResult
    {
        $start  = microtime(true);
        $offset = ($query->page - 1) * $query->limit;

        [$where, $params] = $this->buildWhereClause($query);

        $sortColumn = in_array($query->sortBy, self::ALLOWED_SORT_COLUMNS, true)
            ? $query->sortBy
            : 'occurred_at';
        $sortDir = strtoupper($query->sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $sql = sprintf(
            'SELECT id, url, ip_address, http_method, status_code, response_time_ms, country, occurred_at
             FROM dashboard_read_model
             %s
             ORDER BY %s %s
             LIMIT :limit OFFSET :offset',
            $where,
            $sortColumn,
            $sortDir
        );

        $params['limit']  = $query->limit;
        $params['offset'] = $offset;

        $rows  = $this->connection->fetchAllAssociative($sql, $params);
        $total = $this->countFiltered($where, $params);

        $queryTimeMs = (microtime(true) - $start) * 1000;

        return new DashboardResult(
            records:    array_map(DashboardReadModel::fromRow(...), $rows),
            total:      $total,
            page:       $query->page,
            limit:      $query->limit,
            totalPages: (int) ceil($total / max(1, $query->limit)),
            queryTimeMs: $queryTimeMs,
        );
    }

    public function upsert(array $data): void
    {
        $this->connection->executeStatement(
            'INSERT INTO dashboard_read_model
                (id, url, ip_address, http_method, status_code, response_time_ms, country, occurred_at)
             VALUES
                (:id, :url, :ip_address, :http_method, :status_code, :response_time_ms, :country, :occurred_at)
             ON CONFLICT (id) DO UPDATE SET
                status_code      = EXCLUDED.status_code,
                response_time_ms = EXCLUDED.response_time_ms,
                occurred_at      = EXCLUDED.occurred_at',
            $data
        );
    }

    public function countTotal(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM dashboard_read_model');
    }

    private function buildWhereClause(GetDashboardQuery $query): array
    {
        $conditions = [];
        $params     = [];

        if ($query->filterUrl !== null) {
            $conditions[] = 'url ILIKE :url';
            $params['url'] = '%' . $query->filterUrl . '%';
        }

        if ($query->statusCode !== null) {
            $conditions[] = 'status_code = :status_code';
            $params['status_code'] = $query->statusCode;
        }

        if ($query->country !== null) {
            $conditions[] = 'country = :country';
            $params['country'] = $query->country;
        }

        $where = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);

        return [$where, $params];
    }

    private function countFiltered(string $where, array $params): int
    {
        unset($params['limit'], $params['offset']);
        return (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM dashboard_read_model $where",
            $params
        );
    }
}
