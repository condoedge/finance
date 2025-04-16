<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Models\Traits\HasEventsOnDbInteraction;
use Condoedge\Finance\Models\Traits\HasIntegrityCheck;
use Condoedge\Utils\Models\Model;

abstract class AbstractMainFinanceModel extends Model
{
    use HasIntegrityCheck;
    use HasEventsOnDbInteraction;
    
    /**
     * Check the integrity of the model.
     * Each concrete model must implement this method.
     *
     * @param array|null $ids Specific IDs to check
     * @return void
     */
    abstract public static function checkIntegrity($ids = null): void;
}