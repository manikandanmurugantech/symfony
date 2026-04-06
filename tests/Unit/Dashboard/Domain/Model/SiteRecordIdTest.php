<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard\Domain\Model;

use App\Dashboard\Domain\Model\SiteRecordId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SiteRecordIdTest extends TestCase
{
    public function test_generate_creates_valid_uuid(): void
    {
        $id = SiteRecordId::generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $id->value()
        );
    }

    public function test_from_string_accepts_valid_uuid(): void
    {
        $uuid = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $id   = SiteRecordId::fromString($uuid);

        $this->assertSame($uuid, $id->value());
    }

    public function test_from_string_rejects_invalid_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SiteRecordId::fromString('not-a-valid-uuid');
    }

    public function test_from_string_rejects_empty_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SiteRecordId::fromString('');
    }

    public function test_equals_returns_true_for_same_value(): void
    {
        $uuid = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $a    = SiteRecordId::fromString($uuid);
        $b    = SiteRecordId::fromString($uuid);

        $this->assertTrue($a->equals($b));
    }

    public function test_equals_returns_false_for_different_values(): void
    {
        $a = SiteRecordId::generate();
        $b = SiteRecordId::generate();

        $this->assertFalse($a->equals($b));
    }

    public function test_to_string_returns_uuid_value(): void
    {
        $uuid = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $id   = SiteRecordId::fromString($uuid);

        $this->assertSame($uuid, (string) $id);
    }

    public function test_generate_produces_unique_ids(): void
    {
        $ids = array_map(fn () => SiteRecordId::generate()->value(), range(1, 100));

        $this->assertCount(100, array_unique($ids));
    }
}
