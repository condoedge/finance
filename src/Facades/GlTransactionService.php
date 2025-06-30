<?php

namespace Condoedge\Finance\Facades;

use Illuminate\Support\Facades\Facade;

class GlTransactionService extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return \Condoedge\Finance\Services\GlTransactionServiceInterface::class;
    }
}
