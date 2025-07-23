<?php

namespace Condoedge\Finance\Models;

use Condoedge\Utils\Models\Model;

class TaxableLocation extends Model
{
    protected $table = 'fin_taxable_locations';
    
    protected $casts = [
        'type' => TaxableLocationTypeEnum::class,
    ];
}