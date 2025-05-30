<?php

namespace Condoedge\Finance\Models\GL;

use Condoedge\Finance\Models\AbstractMainFinanceModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlTransaction extends AbstractMainFinanceModel
{
    protected $table = 'fin_transactions';

    protected $fillable = [
        'gl_transaction_number',
        'fiscal_date',
        'fiscal_year',
        'fiscal_period',
        'transaction_type',
        'transaction_description',
        'originating_module_transaction_id',
        'vendor_id',
        'customer_id',
        'team_id',
        'created_by',
        'modified_by'
    ];

    protected $casts = [
        'gl_transaction_number' => 'integer',
        'fiscal_date' => 'date',
        'fiscal_year' => 'integer',
        'transaction_type' => 'integer'
    ];

    // Transaction types
    const TYPE_MANUAL_GL = 1;
    const TYPE_BANK = 2;
    const TYPE_RECEIVABLE = 3;
    const TYPE_PAYABLE = 4;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Auto-generate GL transaction number
            if (!$model->gl_transaction_number) {
                $model->gl_transaction_number = static::getNextTransactionNumber();
            }

            // Auto-calculate fiscal year and period if not set
            if ($model->fiscal_date && !$model->fiscal_year) {
                $model->fiscal_year = FiscalYearSetup::calculateFiscalYear($model->fiscal_date);
            }

            if ($model->fiscal_date && !$model->fiscal_period) {
                $period = FiscalPeriod::getByDate($model->fiscal_date);
                if ($period) {
                    $model->fiscal_period = $period->period_id;
                }
            }

            // Set audit fields
            if (!$model->created_by) {
                $model->created_by = auth()->user()->name ?? 'System';
            }
        });

        static::updating(function ($model) {
            $model->modified_by = auth()->user()->name ?? 'System';
            $model->modified_at = now();
        });
    }

    /**
     * Get GL entries for this transaction
     */
    public function glEntries(): HasMany
    {
        return $this->hasMany(GlEntry::class, 'gl_transaction_id');
    }

    /**
     * Get fiscal period
     */
    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class, 'fiscal_period', 'period_id');
    }

    /**
     * Get vendor (if applicable)
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Get customer (if applicable)
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(\Condoedge\Finance\Models\Customer::class);
    }

    /**
     * Scope by transaction type
     */
    public function scopeByType($query, int $type)
    {
        return $query->where('transaction_type', $type);
    }

    /**
     * Scope for manual GL transactions
     */
    public function scopeManualGl($query)
    {
        return $query->where('transaction_type', self::TYPE_MANUAL_GL);
    }

    /**
     * Get next transaction number
     */
    public static function getNextTransactionNumber(): int
    {
        $lastNumber = static::max('gl_transaction_number') ?? 0;
        return $lastNumber + 1;
    }

    /**
     * Validate transaction (debit = credit)
     */
    public function validateBalance(): bool
    {
        $totalDebits = $this->glEntries()->sum('debit_amount');
        $totalCredits = $this->glEntries()->sum('credit_amount');
        
        return bccomp($totalDebits, $totalCredits, config('kompo-finance.decimal-scale')) === 0;
    }

    /**
     * Get transaction total
     */
    public function getTotal()
    {
        return $this->glEntries()->sum('debit_amount');
    }

    /**
     * Check if period is open for this transaction type
     */
    public function isPeriodOpen(): bool
    {
        if (!$this->fiscalPeriod) {
            return false;
        }

        $moduleType = match($this->transaction_type) {
            self::TYPE_MANUAL_GL => 'GL',
            self::TYPE_BANK => 'BNK',
            self::TYPE_RECEIVABLE => 'RM',
            self::TYPE_PAYABLE => 'PM',
            default => 'GL'
        };

        return $this->fiscalPeriod->isOpenFor($moduleType);
    }

    /**
     * Get transaction type name
     */
    public function getTransactionTypeName(): string
    {
        return match($this->transaction_type) {
            self::TYPE_MANUAL_GL => 'Manual GL Entry',
            self::TYPE_BANK => 'Bank Transaction',
            self::TYPE_RECEIVABLE => 'Receivable Transaction',
            self::TYPE_PAYABLE => 'Payable Transaction',
            default => 'Unknown'
        };
    }

    /**
     * Create manual GL transaction with entries
     */
    public static function createManualTransaction(
        string $description,
        \Carbon\Carbon $fiscalDate,
        array $entries,
        array $additionalData = []
    ): static {
        // Validate entries balance
        $totalDebits = array_sum(array_column($entries, 'debit_amount'));
        $totalCredits = array_sum(array_column($entries, 'credit_amount'));
        
        if (bccomp($totalDebits, $totalCredits, config('kompo-finance.decimal-scale')) !== 0) {
            throw new \Exception('Transaction entries must balance (total debits must equal total credits)');
        }

        // Check if period is open
        $period = FiscalPeriod::getByDate($fiscalDate);
        if (!$period || !$period->isOpenFor('GL')) {
            throw new \Exception('Fiscal period is closed for GL transactions');
        }

        $transaction = static::create(array_merge([
            'fiscal_date' => $fiscalDate,
            'transaction_type' => self::TYPE_MANUAL_GL,
            'transaction_description' => $description,
        ], $additionalData));

        // Create entries
        foreach ($entries as $entry) {
            $transaction->glEntries()->create($entry);
        }

        return $transaction;
    }
}
