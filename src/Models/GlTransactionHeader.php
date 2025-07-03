<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Models\Traits\HasIntegrityCheck;
use Condoedge\Finance\Models\Traits\ValidatesFiscalPeriod;
use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Enums\GlTransactionTypeEnum;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GlTransactionHeader extends AbstractMainFinanceModel
{
    use ValidatesFiscalPeriod;
    use \Condoedge\Utils\Models\Traits\BelongsToTeamTrait;
    
    protected $table = 'fin_gl_transaction_headers';
    
    protected $casts = [
        'fiscal_date' => 'date',
        'fiscal_year' => 'integer',
        'gl_transaction_number' => 'integer',
        'gl_transaction_type' => GlTransactionTypeEnum::class,
        'is_balanced' => 'boolean',
        'is_posted' => 'boolean',
    ];

    public static function boot()
    {
        parent::boot();
        
        // Ensure integrity checks are applied on create/update/delete        
        static::updating(function ($model) {
            if ($model->getOriginal('is_posted')) {
                throw new \Exception(__('translate.error-cannot-modify-posted-transaction'));
            }
        });
        
        static::deleting(function ($model) {
            if ($model->getOriginal('is_posted')) {
                throw new \Exception(__('translate.error-cannot-delete-posted-transaction'));
            }
        });
    }
    
    /**
     * Override fiscal period validation methods
     */
    protected function getFiscalDateForValidation(): ?Carbon
    {
        return $this->fiscal_date ? Carbon::parse($this->fiscal_date) : null;
    }
    
    protected function getModuleForValidation(): ?GlTransactionTypeEnum
    {
        return $this->gl_transaction_type ?? GlTransactionTypeEnum::MANUAL_GL;
    }
    
    public function shouldValidateFiscalPeriod(): bool
    {
        // Skip validation for posted transactions (they're immutable)
        if ($this->is_posted) {
            return false;
        }
        
        return $this->getModuleForValidation() !== null;
    }
    
    /**
     * Relationships
     */
    public function lines()
    {
        return $this->hasMany(GlTransactionLine::class, 'gl_transaction_id');
    }
    
    public function fiscalPeriod()
    {
        return $this->belongsTo(FiscalPeriod::class, 'fiscal_period_id');
    }
    
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Determine fiscal year and period from date
     */
    protected static function determineFiscalData(string $date): array
    {
        $carbonDate = \Carbon\Carbon::parse($date);
        
        $fiscalYear = FiscalYearSetup::getFiscalYearFromDate($carbonDate);
        $period = FiscalPeriod::getPeriodFromDate($carbonDate);
        
        if (!$fiscalYear || !$period) {
            throw new \Exception(__("error-could-not-determine-fiscal-data", ['date' => $date]));
        }
        
        return [
            'fiscal_year' => $fiscalYear,
            'fiscal_period' => $period->id,
        ];
    }
    
    /**
     * Generate transaction ID
     */
    protected static function generateTransactionId(int $fiscalYear, int $transactionType, int $transactionNumber): string
    {
        return sprintf('%04d-%02d-%06d', $fiscalYear, $transactionType, $transactionNumber);
    }
    
    /**
     * Check if transaction can be modified
     */
    public function canBeModified(): bool
    {
        // Cannot modify posted transactions
        if ($this->is_posted) {
            return false;
        }
        
        // Cannot modify if period is closed
        $period = $this->fiscalPeriod;
        if ($period) {
            $module = $this->getModuleForValidation();
            if ($module && !$period->isOpenForModule($module)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Post the transaction (make it final)
     */
    public function post(): void
    {
        if (!$this->is_balanced) {
            throw new \Exception(__('error-cannot-post-unbalanced-transaction'));
        }
        
        if (!$this->canBeModified()) {
            throw new \Exception(__('error-transaction-cannot-be-modified'));
        }
        
        $this->is_posted = true;
        $this->save();
    }
    
    /**
     * Integrity calculations - balance status is handled by triggers
     */
    public static function columnsIntegrityCalculations()
    {
        return [
            // The is_balanced field is calculated by triggers, but we can also verify here
            'is_balanced' => DB::raw('validate_gl_transaction_balance(fin_gl_transaction_headers.id)'),

            'total_debits' => DB::raw('calculate_total_debits(fin_gl_transaction_headers.id)'),
            'total_credits' => DB::raw('calculate_total_credits(fin_gl_transaction_headers.id)'),
        ];
    }
    
    /**
     * Get human-readable transaction type label
     */
    public function getTypeLabelAttribute(): string
    {
        return $this->gl_transaction_type->label();
    }
    
    /**
     * Scopes
     */
    public function scopeManualGl($query)
    {
        return $query->where('gl_transaction_type', GlTransactionTypeEnum::MANUAL_GL);
    }
    
    public function scopeBank($query)
    {
        return $query->where('gl_transaction_type', GlTransactionTypeEnum::BANK);
    }
    
    public function scopeReceivable($query)
    {
        return $query->where('gl_transaction_type', GlTransactionTypeEnum::RECEIVABLE);
    }
    
    public function scopePayable($query)
    {
        return $query->where('gl_transaction_type', GlTransactionTypeEnum::PAYABLE);
    }
    
    public function scopeBalanced($query)
    {
        return $query->where('is_balanced', true);
    }
    
    public function scopeUnbalanced($query)
    {
        return $query->where('is_balanced', false);
    }
    
    public function scopePosted($query)
    {
        return $query->where('is_posted', true);
    }
    
    public function scopeUnposted($query)
    {
        return $query->where('is_posted', false);
    }
    
    public function scopeForTeam($query, $teamId = null)
    {
        $teamId = $teamId ?? currentTeamId();
        return $query->where('team_id', $teamId);
    }

    // Elements
    public function transactionTypePill()
    {
        return _Pill($this->gl_transaction_type->label())->class('text-white')
            ->class($this->gl_transaction_type->colorClass());
    }
}
