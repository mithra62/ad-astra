<?php

namespace AdAstra\Doctor;

enum DoctorStatus: string
{
    case Pass = 'pass';
    case Warn = 'warn';
    case Fail = 'fail';
    case Skip = 'skip';

    /**
     * Ordering weight for aggregating a check's worst outcome
     * (Fail > Warn > Skip > Pass).
     */
    public function weight(): int
    {
        return match ($this) {
            self::Fail => 3,
            self::Warn => 2,
            self::Skip => 1,
            self::Pass => 0,
        };
    }
}
