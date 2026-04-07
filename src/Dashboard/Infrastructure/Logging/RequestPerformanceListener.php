<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Logging;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Monitoring hook: fires after every HTTP response is sent.
 * Logs request duration and flags slow responses above the threshold.
 * Structured JSON output makes it easy to pipe into Datadog / Grafana / ELK.
 */
#[AsEventListener(event: KernelEvents::TERMINATE)]
final class RequestPerformanceListener
{
    private const SLOW_REQUEST_THRESHOLD_MS = 500.0;

    public function __construct(private readonly LoggerInterface $logger) {}

    public function __invoke(TerminateEvent $event): void
    {
        $request  = $event->getRequest();
        $response = $event->getResponse();

        // REQUEST_TIME_FLOAT is set by PHP before the kernel boots
        $startTime   = $request->server->get('REQUEST_TIME_FLOAT', microtime(true));
        $durationMs  = round((microtime(true) - (float) $startTime) * 1000, 2);
        $isSlow      = $durationMs > self::SLOW_REQUEST_THRESHOLD_MS;

        $context = [
            'method'      => $request->getMethod(),
            'path'        => $request->getPathInfo(),
            'status'      => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'slow'        => $isSlow,
        ];

        if ($isSlow) {
            $this->logger->warning(
                sprintf('[SLOW REQUEST] %s %s took %.2fms', $request->getMethod(), $request->getPathInfo(), $durationMs),
                $context
            );
            return;
        }

        $this->logger->info(
            sprintf('[REQUEST] %s %s — %dms', $request->getMethod(), $request->getPathInfo(), $durationMs),
            $context
        );
    }
}
