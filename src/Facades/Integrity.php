<?php

namespace Condoedge\Finance\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void verifyAll()
 * @method static void verifyModel(string $modelClass, mixed $ids = null)
 * @method static void cascadeIntegrity(string $modelClass, mixed $ids = null)
 * @method static \Condoedge\Finance\Services\IntegrityService addRelation(string $parent, string $child)
 * @method static \Condoedge\Finance\Services\IntegrityService removeRelation(string $parent, string $child)
 *
 * @see \Condoedge\Finance\Services\IntegrityService
 */
class Integrity extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'finance.integrity';
    }
}
