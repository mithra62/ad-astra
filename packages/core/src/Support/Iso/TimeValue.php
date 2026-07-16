<?php

namespace AdAstra\Support\Iso;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Immutable time-of-day value object (24-hour, no date component).
 *
 * Dedicated rather than reusing DateTimeImmutable so templates and consumers
 * can't accidentally compare against an arbitrary date anchor.
 */
final class TimeValue
{
    public function __construct(
        public readonly int $hours,
        public readonly int $minutes,
        public readonly int $seconds = 0,
    )
    {
        if ($this->hours < 0 || $this->hours > 23) {
            throw new InvalidArgumentException("Hours out of range: {$this->hours}.");
        }
        if ($this->minutes < 0 || $this->minutes > 59) {
            throw new InvalidArgumentException("Minutes out of range: {$this->minutes}.");
        }
        if ($this->seconds < 0 || $this->seconds > 59) {
            throw new InvalidArgumentException("Seconds out of range: {$this->seconds}.");
        }
    }

    /**
     * Parse a canonical-form string ("HH:MM" or "HH:MM:SS") into a TimeValue.
     * Throws on any non-canonical input. Use Time::prepareForStorage to
     * canonicalize first if needed.
     */
    public static function fromCanonical(string $canonical): self
    {
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $canonical, $m)) {
            throw new InvalidArgumentException("Not a canonical time: {$canonical}.");
        }
        return new self((int)$m[1], (int)$m[2], isset($m[3]) ? (int)$m[3] : 0);
    }

    /**
     * Canonical "HH:MM" or "HH:MM:SS" string. Always returns the seconds
     * component when non-zero; callers wanting "always HH:MM:SS" should
     * format() with 'H:i:s' instead.
     */
    public function canonical(): string
    {
        $base = sprintf('%02d:%02d', $this->hours, $this->minutes);
        return $this->seconds > 0 ? $base . sprintf(':%02d', $this->seconds) : $base;
    }

    /**
     * Format using PHP date()-style tokens, e.g. "g:i A" → "9:30 AM".
     * Backed by DateTimeImmutable anchored to a sentinel date that callers
     * never see.
     */
    public function format(string $phpFormat): string
    {
        return $this->toDateTimeImmutable()->format($phpFormat);
    }

    private function toDateTimeImmutable(): DateTimeImmutable
    {
        return new DateTimeImmutable(sprintf(
            '1970-01-01 %02d:%02d:%02d',
            $this->hours,
            $this->minutes,
            $this->seconds,
        ));
    }

    public function toSeconds(): int
    {
        return $this->toMinutes() * 60 + $this->seconds;
    }

    public function toMinutes(): int
    {
        return $this->hours * 60 + $this->minutes;
    }
}
