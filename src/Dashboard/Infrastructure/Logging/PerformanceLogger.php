<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Logging;

use Psr\Log\LoggerInterface;

/**
 * Performance measurement hook.
 * Logs query duration, flags slow queries, and emits monitoring-ready structured logs.
 */
final class PerformanceLogger
{
    private const SLOW_QUERY_THRESHOLD_MS = 200.0;

    public function __construct(private readonly LoggerInterface $logger) {}

    public function logQuery(string $queryName, float $durationMs, array $context = []): void
    {
        $payload = array_merge($context, [
            'query'       => $queryName,
            'duration_ms' => round($durationMs, 2),
            'slow'        => $durationMs > self::SLOW_QUERY_THRESHOLD_MS,
        ]);

        if ($durationMs > self::SLOW_QUERY_THRESHOLD_MS) {
            $this->logger->warning(
                sprintf('[SLOW QUERY] %s took %.2fms (threshold: %dms)', $queryName, $durationMs, self::SLOW_QUERY_THRESHOLD_MS),
                $payload
            );
        } else {
            $this->logger->info(
                sprintf('[QUERY] %s completed in %.2fms', $queryName, $durationMs),
                $payload
            );
        }
    }

    public function logCommand(string $commandName, float $durationMs, array $context = []): void
    {
        $this->logger->info(
            sprintf('[COMMAND] %s executed in %.2fms', $commandName, $durationMs),
            array_merge($context, [
                'command'     => $commandName,
                'duration_ms' => round($durationMs, 2),
            ])
        );
    }

    public function logCacheEvent(string $event, string $key): void
    {
        $this->logger->debug(sprintf('[CACHE] %s: %s', strtoupper($event), $key), [
            'cache_event' => $event,
            'cache_key'   => $key,
        ]);
    }
}
