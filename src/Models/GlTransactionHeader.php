<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Models\Traits\HasIntegrityCheck;
use Condoedge\Finance\Models\Traits\ValidatesFiscalPeriod;
use Condoedge\Finance\Casts\SafeDecimal;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GlTransactionHeader extends AbstractMainFinanceModel
{
    use HasIntegrityCheck;
    use ValidatesFiscalPeriod;
    use \Condoedge\Utils\Models\Traits\BelongsToTeamTrait;
    
    protected $table = 'fin_gl_transaction_headers';
    protected $primaryKey = 'gl_transaction_id';
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'gl_transaction_id',
        'gl_transaction_number',
        'fiscal_date',
        'fiscal_year',
        'fiscal_period',
        'gl_transaction_type',
        'transaction_description',
        'originating_module_transaction_id',
        'vendor_id',
        'customer_id',
        'team_id',
        'is_balanced',
        'is_posted',
    ];
    
    protected $casts = [
        'fiscal_date' => 'date',
        'fiscal_year' => 'integer',
        'gl_transaction_number' => 'integer',
        'gl_transaction_type' => 'integer',
        'is_balanced' => 'boolean',
        'is_posted' => 'boolean',
    ];
    
    // Transaction Type Constants
    const TYPE_MANUAL_GL = 1;
    const TYPE_BANK = 2;
    const TYPE_RECEIVABLE = 3;
    const TYPE_PAYABLE = 4;
    
    /**
     * Override fiscal period validation methods
     */
    protected function getFiscalDateForValidation(): ?Carbon
    {
        return $this->fiscal_date ? Carbon::parse($this->fiscal_date) : null;
    }
    
    protected function getModuleForValidation(): ?string
    {
        return $this->mapTransactionTypeToModule($this->gl_transaction_type ?? self::TYPE_MANUAL_GL);
    }
    
    protected function shouldValidateFiscalPeriod(): bool
    {
        // Skip validation for posted transactions (they're immutable)
        if ($this->is_posted) {
            return false;
        }
        
        return parent::shouldValidateFiscalPeriod();
    }
    
    /**
     * Map GL transaction type to fiscal module
     */
    protected function mapTransactionTypeToModule(int $transactionType): string
    {
        return match($transactionType) {
            self::TYPE_MANUAL_GL => 'GL',
            self::TYPE_BANK => 'BNK',
            self::TYPE_RECEIVABLE => 'RM',
            self::TYPE_PAYABLE => 'PM',
            default => 'GL',
        };
    }
    
    /**
     * Relationships
     */
    public function lines()
    {
        return $this->hasMany(GlTransactionLine::class, 'gl_transaction_id', 'gl_transaction_id');
    }
    
    public function fiscalPeriod()
    {
        return $this->belongsTo(FiscalPeriod::class, 'fiscal_period', 'period_id');
    }
    
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
    
    /**
     * Create a new GL transaction with proper sequencing
     */
    public static function createTransaction(array $data): self
    {
        // Generate transaction number if not provided
        if (!isset($data['gl_transaction_number'])) {
            $data['gl_transaction_number'] = static::getNextTransactionNumber();
        }
        
        // Auto-determine fiscal year and period if not provided
        if (!isset($data['fiscal_year']) || !isset($data['fiscal_period'])) {
            $fiscalData = static::determineFiscalData($data['fiscal_date']);
            $data['fiscal_year'] = $data['fiscal_year'] ?? $fiscalData['fiscal_year'];
            $data['fiscal_period'] = $data['fiscal_period'] ?? $fiscalData['fiscal_period'];
        }
        
        // Generate transaction ID if not provided
        if (!isset($data['gl_transaction_id'])) {
            $data['gl_transaction_id'] = static::generateTransactionId(
                $data['fiscal_year'],
                $data['gl_transaction_type'],
                $data['gl_transaction_number']
            );
        }
        
        return static::create($data);
    }
    
    /**
     * Get next transaction number
     */
    protected static function getNextTransactionNumber(): int
    {
        return DB::selectOne('SELECT get_next_gl_transaction_number(?, ?) as next_number', [
            'GL_TRANSACTION',
            null // Global sequence, not per fiscal year
        ])->next_number;
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
            throw new \Exception('Could not determine fiscal year or period for date: ' . $date);
        }
        
        return [
            'fiscal_year' => $fiscalYear,
            'fiscal_period' => $period->period_id,
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
        if ($period && !$period->isOpenForTransactionType($this->gl_transaction_type)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Post the transaction (make it final)
     */
    public function post(): void
    {
        if (!$this->is_balanced) {
            throw new \Exception('Cannot post unbalanced transaction');
        }
        
        if (!$this->canBeModified()) {
            throw new \Exception('Transaction cannot be modified');
        }
        
        $this->update(['is_posted' => true]);
    }
    
    /**
     * Get total debits
     */
    public function getTotalDebitsAttribute(): SafeDecimal
    {
        return new SafeDecimal($this->lines()->sum('debit_amount'));
    }
    
    /**
     * Get total credits  
     */
    public function getTotalCreditsAttribute(): SafeDecimal
    {
        return new SafeDecimal($this->lines()->sum('credit_amount'));
    }
    
    /**
     * Integrity calculations - balance status is handled by triggers
     */
    public static function columnsIntegrityCalculations()
    {
        return [
            // The is_balanced field is calculated by triggers, but we can also verify here
            'is_balanced' => DB::raw('validate_gl_transaction_balance(fin_gl_transaction_headers.gl_transaction_id)'),
        ];
    }
    
    /**
     * Scopes
     */
    public function scopeManualGl($query)
    {
        return $query->where('gl_transaction_type', self::TYPE_MANUAL_GL);
    }
    
    public function scopeBank($query)
    {
        return $query->where('gl_transaction_type', self::TYPE_BANK);
    }
    
    public function scopeReceivable($query)
    {
        return $query->where('gl_transaction_type', self::TYPE_RECEIVABLE);
    }
    
    public function scopePayable($query)
    {
        return $query->where('gl_transaction_type', self::TYPE_PAYABLE);
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
}
