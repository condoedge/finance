<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Models\Traits\HasIntegrityCheck;

class FiscalPeriod extends AbstractMainFinanceModel
{
    use HasIntegrityCheck;
    use \Condoedge\Utils\Models\Traits\BelongsToTeamTrait;
    
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
        'team_id',
        'is_open_gl',
        'is_open_bnk',
        'is_open_rm',
        'is_open_pm',
    ];
    
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
                    ->where('start_date', '<=', $date)
                    ->where('end_date', '>=', $date)
                    ->first();
    }
    
    /**
     * Check if period is open for specific module
     */
    public function isOpenForModule(string $module): bool
    {
        return match(strtoupper($module)) {
            'GL' => $this->is_open_gl,
            'BNK' => $this->is_open_bnk,
            'RM' => $this->is_open_rm,
            'PM' => $this->is_open_pm,
            default => false,
        };
    }
    
    /**
     * Check if period is open for transaction type
     */
    public function isOpenForTransactionType(int $transactionType): bool
    {
        return match($transactionType) {
            1 => $this->is_open_gl,  // Manual GL
            2 => $this->is_open_bnk, // Bank
            3 => $this->is_open_rm,  // Receivable
            4 => $this->is_open_pm,  // Payable
            default => false,
        };
    }
    
    /**
     * Close period for specific module
     */
    public function closeForModule(string $module): void
    {
        $field = 'is_open_' . strtolower($module);
        if (in_array($field, $this->fillable)) {
            $this->update([$field => false]);
        }
    }
    
    /**
     * Open period for specific module
     */
    public function openForModule(string $module): void
    {
        $field = 'is_open_' . strtolower($module);
        if (in_array($field, $this->fillable)) {
            $this->update([$field => true]);
        }
    }
    
    /**
     * No calculated columns for this model
     */
    public static function columnsIntegrityCalculations()
    {
        return [];
    }
}
