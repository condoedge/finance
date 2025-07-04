<?php

namespace Condoedge\Finance\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @mixin \Condoedge\Finance\Services\IntegrityChecker::class
 */
class IntegrityChecker extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'integrity-checker';
    }
}
