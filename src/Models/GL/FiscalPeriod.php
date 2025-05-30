<?php

namespace Condoedge\Finance\Models\GL;

use Condoedge\Finance\Models\AbstractMainFinanceModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalPeriod extends AbstractMainFinanceModel
{
    protected $table = 'fin_fiscal_periods';
    protected $primaryKey = 'period_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'period_id',
        'fiscal_year',
        'period_number',
        'start_date',
        'end_date',
        'is_open_gl',
        'is_open_bnk',
        'is_open_rm',
        'is_open_pm'
    ];

    protected $casts = [
        'fiscal_year' => 'integer',
        'period_number' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_open_gl' => 'boolean',
        'is_open_bnk' => 'boolean',
        'is_open_rm' => 'boolean',
        'is_open_pm' => 'boolean'
    ];

    /**
     * Get GL transactions for this period
     */
    public function glTransactions(): HasMany
    {
        return $this->hasMany(GlTransaction::class, 'fiscal_period', 'period_id');
    }

    /**
     * Check if period is open for specific module
     */
    public function isOpenFor(string $moduleType): bool
    {
        return match(strtoupper($moduleType)) {
            'GL' => $this->is_open_gl,
            'BNK' => $this->is_open_bnk,
            'RM' => $this->is_open_rm,
            'PM' => $this->is_open_pm,
            default => false
        };
    }

    /**
     * Close period for specific module
     */
    public function closeFor(string $moduleType): bool
    {
        $field = match(strtoupper($moduleType)) {
            'GL' => 'is_open_gl',
            'BNK' => 'is_open_bnk',
            'RM' => 'is_open_rm',
            'PM' => 'is_open_pm',
            default => null
        };

        if ($field) {
            $this->{$field} = false;
            return $this->save();
        }

        return false;
    }

    /**
     * Open period for specific module
     */
    public function openFor(string $moduleType): bool
    {
        $field = match(strtoupper($moduleType)) {
            'GL' => 'is_open_gl',
            'BNK' => 'is_open_bnk',
            'RM' => 'is_open_rm',
            'PM' => 'is_open_pm',
            default => null
        };

        if ($field) {
            $this->{$field} = true;
            return $this->save();
        }

        return false;
    }

    /**
     * Get period by date
     */
    public static function getByDate($date)
    {
        return static::where('start_date', '<=', $date)
                    ->where('end_date', '>=', $date)
                    ->first();
    }

    /**
     * Get current period
     */
    public static function getCurrentPeriod()
    {
        return static::getByDate(now());
    }
}
