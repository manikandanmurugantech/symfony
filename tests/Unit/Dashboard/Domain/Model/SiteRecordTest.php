<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard\Domain\Model;

use App\Dashboard\Domain\Event\SiteRecordCreated;
use App\Dashboard\Domain\Model\SiteRecord;
use App\Dashboard\Domain\Model\SiteRecordId;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SiteRecordTest extends TestCase
{
    private SiteRecordId $id;

    protected function setUp(): void
    {
        $this->id = SiteRecordId::generate();
    }

    public function test_create_builds_valid_aggregate(): void
    {
        $record = $this->makeRecord();

        $this->assertSame('/api/users', $record->url());
        $this->assertSame('196.168.1.100', $record->ipAddress());
        $this->assertSame('GET', $record->httpMethod());
        $this->assertSame(200, $record->statusCode());
        $this->assertSame(120.5, $record->responseTimeMs());
        $this->assertSame('IN', $record->country());
    }

    public function test_create_raises_site_record_created_event(): void
    {
        $record = $this->makeRecord();
        $events = $record->releaseEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(SiteRecordCreated::class, $events[0]);
    }

    public function test_domain_event_contains_correct_data(): void
    {
        $record = $this->makeRecord();
        $events = $record->releaseEvents();

        /** @var SiteRecordCreated $event */
        $event = $events[0];

        $this->assertSame($this->id->value(), $event->siteRecordId);
        $this->assertSame('/api/users', $event->url);
        $this->assertSame(200, $event->statusCode);
        $this->assertSame('IN', $event->country);
    }

    public function test_release_events_clears_event_list(): void
    {
        $record = $this->makeRecord();

        $record->releaseEvents(); // first release
        $second = $record->releaseEvents();

        $this->assertEmpty($second);
    }

    public function test_create_rejects_empty_url(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SiteRecord::create(
            $this->id, '', '196.168.1.1', 'Mozilla',
            'GET', 200, 100.0, 'IN', new DateTimeImmutable()
        );
    }

    public function test_create_rejects_negative_response_time(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SiteRecord::create(
            $this->id, '/home', '196.168.1.1', 'Mozilla',
            'GET', 200, -1.0, 'IN', new DateTimeImmutable()
        );
    }

    public function test_create_rejects_invalid_http_method(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SiteRecord::create(
            $this->id, '/home', '196.168.1.1', 'Mozilla',
            'INVALID', 200, 100.0, 'IN', new DateTimeImmutable()
        );
    }

    public function test_create_accepts_all_valid_http_methods(): void
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $record = SiteRecord::create(
                SiteRecordId::generate(),
                '/test', '1.2.3.4', 'UA', $method, 200, 50.0, 'US',
                new DateTimeImmutable()
            );
            $this->assertSame($method, $record->httpMethod());
        }
    }

    public function test_id_is_preserved(): void
    {
        $record = $this->makeRecord();
        $this->assertTrue($this->id->equals($record->id()));
    }

    // -------------------------------------------------------------------------
    private function makeRecord(array $overrides = []): SiteRecord
    {
        return SiteRecord::create(
            $overrides['id']              ?? $this->id,
            $overrides['url']             ?? '/api/users',
            $overrides['ipAddress']       ?? '196.168.1.100',
            $overrides['userAgent']       ?? 'Mozilla/5.0',
            $overrides['httpMethod']      ?? 'GET',
            $overrides['statusCode']      ?? 200,
            $overrides['responseTimeMs']  ?? 120.5,
            $overrides['country']         ?? 'IN',
            $overrides['occurredAt']      ?? new DateTimeImmutable(),
        );
    }
}
