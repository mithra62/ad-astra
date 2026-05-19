<?php

namespace Tests\Unit\Support\Iso;

use App\Support\Iso\TimeValue;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TimeValueTest extends TestCase
{
    public function test_constructor_accepts_valid_components(): void
    {
        $t = new TimeValue(9, 30, 15);
        $this->assertSame(9, $t->hours);
        $this->assertSame(30, $t->minutes);
        $this->assertSame(15, $t->seconds);
    }

    public function test_constructor_rejects_out_of_range_hours(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TimeValue(24, 0);
    }

    public function test_constructor_rejects_out_of_range_minutes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TimeValue(0, 60);
    }

    public function test_constructor_rejects_out_of_range_seconds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TimeValue(0, 0, 60);
    }

    public function test_canonical_without_seconds(): void
    {
        $this->assertSame('09:30', (new TimeValue(9, 30))->canonical());
    }

    public function test_canonical_with_seconds(): void
    {
        $this->assertSame('09:30:15', (new TimeValue(9, 30, 15))->canonical());
    }

    public function test_from_canonical_parses_hh_mm(): void
    {
        $t = TimeValue::fromCanonical('09:30');
        $this->assertSame(9, $t->hours);
        $this->assertSame(30, $t->minutes);
        $this->assertSame(0, $t->seconds);
    }

    public function test_from_canonical_parses_hh_mm_ss(): void
    {
        $t = TimeValue::fromCanonical('23:59:59');
        $this->assertSame(23, $t->hours);
        $this->assertSame(59, $t->minutes);
        $this->assertSame(59, $t->seconds);
    }

    public function test_from_canonical_rejects_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TimeValue::fromCanonical('25:00');
    }

    public function test_from_canonical_rejects_non_padded(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TimeValue::fromCanonical('9:30');
    }

    public function test_format_am(): void
    {
        $this->assertSame('9:30 AM', (new TimeValue(9, 30))->format('g:i A'));
    }

    public function test_format_pm(): void
    {
        $this->assertSame('5:45 PM', (new TimeValue(17, 45))->format('g:i A'));
    }

    public function test_to_minutes(): void
    {
        $this->assertSame(570, (new TimeValue(9, 30))->toMinutes());
        $this->assertSame(0, (new TimeValue(0, 0))->toMinutes());
    }

    public function test_to_seconds(): void
    {
        $this->assertSame(34215, (new TimeValue(9, 30, 15))->toSeconds());
    }
}
