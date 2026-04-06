<?php

declare(strict_types=1);

namespace App\Dashboard\Domain\Model;

use App\Dashboard\Domain\Event\SiteRecordCreated;
use DateTimeImmutable;
use InvalidArgumentException;

final class SiteRecord
{
    private array $domainEvents = [];

    private function __construct(
        private readonly SiteRecordId      $id,
        private readonly string            $url,
        private readonly string            $ipAddress,
        private readonly string            $userAgent,
        private readonly string            $httpMethod,
        private readonly int               $statusCode,
        private readonly float             $responseTimeMs,
        private readonly string            $country,
        private readonly DateTimeImmutable $occurredAt,
    ) {}

    public static function create(
        SiteRecordId $id,
        string       $url,
        string       $ipAddress,
        string       $userAgent,
        string       $httpMethod,
        int          $statusCode,
        float        $responseTimeMs,
        string       $country,
        DateTimeImmutable $occurredAt,
    ): self {
        if (empty($url)) {
            throw new InvalidArgumentException('URL cannot be empty.');
        }

        if ($responseTimeMs < 0) {
            throw new InvalidArgumentException('Response time cannot be negative.');
        }

        if (!in_array($httpMethod, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            throw new InvalidArgumentException(sprintf('Invalid HTTP method: %s', $httpMethod));
        }

        $record = new self(
            $id, $url, $ipAddress, $userAgent,
            $httpMethod, $statusCode, $responseTimeMs,
            $country, $occurredAt
        );

        $record->raise(new SiteRecordCreated(
            $id->value(),
            $url,
            $ipAddress,
            $httpMethod,
            $statusCode,
            $responseTimeMs,
            $country,
            $occurredAt,
        ));

        return $record;
    }

    public function id(): SiteRecordId
    {
        return $this->id;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function ipAddress(): string
    {
        return $this->ipAddress;
    }

    public function userAgent(): string
    {
        return $this->userAgent;
    }

    public function httpMethod(): string
    {
        return $this->httpMethod;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function responseTimeMs(): float
    {
        return $this->responseTimeMs;
    }

    public function country(): string
    {
        return $this->country;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    private function raise(object $event): void
    {
        $this->domainEvents[] = $event;
    }
}
