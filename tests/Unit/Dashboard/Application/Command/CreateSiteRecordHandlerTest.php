<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard\Application\Command;

use App\Dashboard\Application\Command\CreateSiteRecord\CreateSiteRecordCommand;
use App\Dashboard\Application\Command\CreateSiteRecord\CreateSiteRecordHandler;
use App\Dashboard\Domain\Event\SiteRecordCreated;
use App\Dashboard\Domain\Model\SiteRecord;
use App\Dashboard\Domain\Repository\SiteRecordRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class CreateSiteRecordHandlerTest extends TestCase
{
    private SiteRecordRepositoryInterface&MockObject $repository;
    private MessageBusInterface&MockObject           $eventBus;
    private CreateSiteRecordHandler                  $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(SiteRecordRepositoryInterface::class);
        $this->eventBus   = $this->createMock(MessageBusInterface::class);
        $this->handler    = new CreateSiteRecordHandler($this->repository, $this->eventBus);
    }

    public function test_saves_site_record_to_repository(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(SiteRecord::class));

        $this->eventBus
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        ($this->handler)($this->makeCommand());
    }

    public function test_returns_generated_uuid_string(): void
    {
        $this->repository->method('save');
        $this->eventBus
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $id = ($this->handler)($this->makeCommand());

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $id
        );
    }

    public function test_dispatches_site_record_created_event(): void
    {
        $this->repository->method('save');

        $this->eventBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SiteRecordCreated::class))
            ->willReturn(new Envelope(new \stdClass()));

        ($this->handler)($this->makeCommand());
    }

    public function test_dispatched_event_contains_correct_url(): void
    {
        $this->repository->method('save');

        $this->eventBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(fn ($event) =>
                $event instanceof SiteRecordCreated && $event->url === '/api/test'
            ))
            ->willReturn(new Envelope(new \stdClass()));

        ($this->handler)($this->makeCommand(url: '/api/test'));
    }

    public function test_two_invocations_produce_unique_ids(): void
    {
        $this->repository->method('save');
        $this->eventBus
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $id1 = ($this->handler)($this->makeCommand());
        $id2 = ($this->handler)($this->makeCommand());

        $this->assertNotSame($id1, $id2);
    }

    // -------------------------------------------------------------------------
    private function makeCommand(string $url = '/home'): CreateSiteRecordCommand
    {
        return new CreateSiteRecordCommand(
            url:            $url,
            ipAddress:      '203.0.113.10',
            userAgent:      'Mozilla/5.0',
            httpMethod:     'GET',
            statusCode:     200,
            responseTimeMs: 85.3,
            country:        'IN',
        );
    }
}
