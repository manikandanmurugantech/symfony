<?php

declare(strict_types=1);

namespace App\UI\Http;

use App\Dashboard\Application\Query\GetDashboard\GetDashboardQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController
{
    public function __construct(private readonly MessageBusInterface $queryBus) {}

    #[Route('/api/dashboard', name: 'dashboard_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $query = new GetDashboardQuery(
            page:       max(1, (int) $request->query->get('page', 1)),
            limit:      min(200, max(1, (int) $request->query->get('limit', 50))),
            sortBy:     $request->query->get('sort_by', 'occurred_at'),
            sortDir:    $request->query->get('sort_dir', 'DESC'),
            filterUrl:  $request->query->get('url'),
            statusCode: $request->query->get('status_code') !== null
                ? (int) $request->query->get('status_code')
                : null,
            country:    $request->query->get('country'),
        );

        $envelope = $this->queryBus->dispatch($query);
        $result   = $envelope->last(HandledStamp::class)->getResult();

        return new JsonResponse([
            'data' => array_map(fn ($r) => [
                'id'               => $r->id,
                'url'              => $r->url,
                'ip_address'       => $r->ipAddress,
                'http_method'      => $r->httpMethod,
                'status_code'      => $r->statusCode,
                'response_time_ms' => $r->responseTimeMs,
                'country'          => $r->country,
                'occurred_at'      => $r->occurredAt,
            ], $result->records),
            'meta' => [
                'total'       => $result->total,
                'page'        => $result->page,
                'limit'       => $result->limit,
                'total_pages' => $result->totalPages,
                'query_ms'    => round($result->queryTimeMs, 2),
            ],
        ], Response::HTTP_OK);
    }

    #[Route('/api/dashboard/stats', name: 'dashboard_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Use /api/dashboard for paginated table data.',
            'filters' => [
                'page', 'limit', 'sort_by', 'sort_dir', 'url', 'status_code', 'country',
            ],
        ]);
    }
}
