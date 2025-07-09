<?php

namespace Condoedge\Finance\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Payment Term Service Facade
 *
 * @see \Condoedge\Finance\Services\PaymentTerm\PaymentTermServiceInterface
 * @mixin \Condoedge\Finance\Services\PaymentTerm\PaymentTermServiceInterface
 */
class PaymentTermService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Condoedge\Finance\Services\PaymentTerm\PaymentTermServiceInterface::class;
    }
}
