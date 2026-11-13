<?php

namespace Baspa\EnergyZero\Tests;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final class FixedClock implements ClockInterface
{
    private DateTimeImmutable $now;

    public function __construct(string $now)
    {
        $this->now = new DateTimeImmutable($now);
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
