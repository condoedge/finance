<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Models\Traits\HasEventsOnDbInteraction;
use Condoedge\Finance\Models\Traits\HasIntegrityCheck;
use Condoedge\Finance\Models\Traits\HasSqlColumnCalculation;
use Condoedge\Utils\Models\Model;
use Illuminate\Support\Facades\DB;

abstract class AbstractMainFinanceModel extends Model
{
    use HasSqlColumnCalculation;
    use HasIntegrityCheck;
    use HasEventsOnDbInteraction;

    public function getAttribute($key)
    {
        if (strpos($key, 'sql_') === 0) {
            return $this->getSqlTableColumn(static::columnsIntegrityCalculations()[$key] ?? $key, substr($key, 4));
        }

        if (strpos($key, 'abs_') === 0) {
            return abs($this->getAttribute(substr($key, 4)));
        }

        return parent::getAttribute($key);
    }
    
    /**
     * Check the integrity of the model.
     * Each concrete model must implement this method.
     *
     * @param array|null $ids Specific IDs to check
     * @return void
     */
    public final static function checkIntegrity($ids = null): void
    {
        DB::table((new static)->getTable())
            ->when($ids, function ($query) use ($ids) {
                return $query->whereIn('id', $ids);
            })
            ->update(static::columnsIntegrityCalculations());
    }

    public static function columnsIntegrityCalculations()
    {
        return [];
    }
}