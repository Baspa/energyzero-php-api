<?php

namespace Baspa\EnergyZero\Enums;

enum Interval: int
{
    case QUARTER = 3;
    case HOUR = 4;
    case DAY = 5;

    public function apiValue(): string
    {
        return match ($this) {
            self::QUARTER => 'INTERVAL_QUARTER',
            self::HOUR => 'INTERVAL_HOUR',
            self::DAY => 'INTERVAL_DAY',
        };
    }
}
