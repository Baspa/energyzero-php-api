<?php

namespace Baspa\EnergyZero\Enums;

enum PriceType: string
{
    case MARKET = 'base';
    case MARKET_WITH_VAT = 'base_with_vat';
    case ALL_IN = 'all_in';
    case ALL_IN_WITH_VAT = 'all_in_with_vat';
}
