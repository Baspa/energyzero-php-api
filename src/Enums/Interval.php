<?php

namespace Baspa\EnergyZero\Enums;

enum Interval: int
{
    case QUARTER = 3;
    case HOUR = 4;
    case DAY = 5;
    case WEEK = 6;
    case MONTH = 7;
    case YEAR = 8;
}
