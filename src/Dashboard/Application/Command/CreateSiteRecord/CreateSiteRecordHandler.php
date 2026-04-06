<?php

declare(strict_types=1);

namespace App\Dashboard\Application\Command\CreateSiteRecord;

use App\Dashboard\Domain\Model\SiteRecord;
use App\Dashboard\Domain\Model\SiteRecordId;
use App\Dashboard\Domain\Repository\SiteRecordRepositoryInterface;
use DateTimeImmutable;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class CreateSiteRecordHandler
{
    public function __construct(
        private readonly SiteRecordRepositoryInterface $repository,
        private readonly MessageBusInterface           $eventBus,
    ) {}

    public function __invoke(CreateSiteRecordCommand $command): string
    {
        $id     = SiteRecordId::generate();
        $record = SiteRecord::create(
            $id,
            $command->url,
            $command->ipAddress,
            $command->userAgent,
            $command->httpMethod,
            $command->statusCode,
            $command->responseTimeMs,
            $command->country,
            new DateTimeImmutable(),
        );

        $this->repository->save($record);

        // Dispatch all domain events to the event bus (async transport)
        foreach ($record->releaseEvents() as $event) {
            $this->eventBus->dispatch($event);
        }

        return $id->value();
    }
}
