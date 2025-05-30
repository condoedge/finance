<?php

namespace Condoedge\Finance\Models\GL;

use Condoedge\Finance\Models\AbstractMainFinanceModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalYearSetup extends AbstractMainFinanceModel
{
    protected $table = 'fin_fiscal_year_setup';

    protected $fillable = [
        'company_fiscal_start_date',
        'is_active',
        'notes'
    ];

    protected $casts = [
        'company_fiscal_start_date' => 'date',
        'is_active' => 'boolean'
    ];

    /**
     * Get all fiscal periods for this fiscal year setup
     */
    public function fiscalPeriods(): HasMany
    {
        return $this->hasMany(FiscalPeriod::class, 'fiscal_year', 'id');
    }

    /**
     * Get the active fiscal year setup
     */
    public static function getActive()
    {
        return static::where('is_active', true)->first();
    }

    /**
     * Calculate fiscal year based on date
     */
    public static function calculateFiscalYear($date)
    {
        $setup = static::getActive();
        if (!$setup) {
            return null;
        }

        $fiscalStartMonth = $setup->company_fiscal_start_date->month;
        $fiscalStartDay = $setup->company_fiscal_start_date->day;
        
        $inputDate = \Carbon\Carbon::parse($date);
        
        // If the date is before the fiscal start date in the same year,
        // it belongs to the previous fiscal year
        if ($inputDate->month < $fiscalStartMonth || 
            ($inputDate->month == $fiscalStartMonth && $inputDate->day < $fiscalStartDay)) {
            return $inputDate->year;
        }
        
        return $inputDate->year + 1;
    }
}
