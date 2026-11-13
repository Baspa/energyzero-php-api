<?php

namespace Baspa\EnergyZero\Enums;

enum EnergyType: int
{
    case ELECTRICITY = 1;
    case GAS = 3;

    public function apiValue(): string
    {
        return match ($this) {
            self::ELECTRICITY => 'ENERGY_TYPE_ELECTRICITY',
            self::GAS => 'ENERGY_TYPE_GAS',
        };
    }
}
