<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Enums\GlTransactionTypeEnum;
use Condoedge\Finance\Models\Traits\HasIntegrityCheck;

class FiscalPeriod extends AbstractMainFinanceModel
{
    use HasIntegrityCheck;
    use \Condoedge\Utils\Models\Traits\BelongsToTeamTrait;

    protected $table = 'fin_fiscal_periods';

    protected $casts = [
        'fiscal_year' => 'integer',
        'period_number' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_open_gl' => 'boolean',
        'is_open_bnk' => 'boolean',
        'is_open_rm' => 'boolean',
        'is_open_pm' => 'boolean',
    ];

    /**
     * Get fiscal period from date for a specific team
     */
    public static function getPeriodFromDate(\Carbon\Carbon $date, int $teamId = null): ?self
    {
        $teamId = $teamId ?? currentTeamId();

        return static::where('team_id', $teamId)
                    ->whereDate('start_date', '<=', $date)
                    ->whereDate('end_date', '>=', $date)
                    ->first();
    }

    /**
     * Check if period is open for specific module
     */
    public function isOpenForModule(GlTransactionTypeEnum $module): bool
    {
        $column = $module->getFiscalPeriodOpenField();

        return $this->{$column} ?? false;
    }

    /**
     * Close period for specific module
     */
    public function closeForModule(GlTransactionTypeEnum $module): void
    {
        $column = $module->getFiscalPeriodOpenField();

        $this->$column = false;
        $this->save();
    }

    /**
     * Open period for specific module
     */
    public function openForModule(GlTransactionTypeEnum $module): void
    {
        $column = $module->getFiscalPeriodOpenField();

        $this->$column = true;
        $this->save();
    }

    /**
     * No calculated columns for this model
     */
    public static function columnsIntegrityCalculations()
    {
        return [];
    }
}
