<?php

namespace Condoedge\Finance\Models;

enum TaxableLocationTypeEnum: int
{
    case FEDERAL = 1;
    case PROVINCE = 2;
    case TERRITORY = 3;
}
