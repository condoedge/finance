<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Casts\SafeDecimalCast;
use Condoedge\Finance\Models\Traits\HasEventsOnDbInteraction;
use Condoedge\Finance\Models\Traits\HasIntegrityCheck;
use Condoedge\Finance\Models\Traits\HasSqlColumnCalculation;
use Condoedge\Utils\Models\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Random\Engine\Secure;

abstract class AbstractMainFinanceModel extends Model
{
    use HasSqlColumnCalculation;
    use HasIntegrityCheck;
    use HasEventsOnDbInteraction;

    public function getAttribute($key)
    {
        if (strpos($key, 'sql_') === 0) {
            $column = substr($key, 4);

            return $this->getSqlColumnCalculation(static::columnsIntegrityCalculations()[$column] ?? $column, $column);
        }

        if (strpos($key, 'abs_') === 0) {
            return abs($this->getAttribute(substr($key, 4)));
        }

        $value = parent::getAttribute($key);

        // If it's a decimal value, and it's not in the casts, we need to cast it. We could get an error, but we prefer to get an error before saving wrong data. With some luck, the error will be caught by the tests.
        if (is_decimal($value)) {
            Log::alert('Unmanaged decimal into:' . basename(static::class) . " ($key)", [
                'id' => $this->id,
                'current_user_id' => auth()->id(),
                'current_team_id' => currentTeamId(),
            ]);

            if (config('kompo-finance.automatic-handle-of-unmanaged-decimals')) {
                throw new \Exception('Unmanaged decimal value');
            }
        }

        return $value;
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
        if (count(static::columnsIntegrityCalculations()) === 0) {
            return;
        }

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