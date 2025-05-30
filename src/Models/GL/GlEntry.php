<?php

namespace Condoedge\Finance\Models\GL;

use Condoedge\Finance\Models\AbstractMainFinanceModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlEntry extends AbstractMainFinanceModel
{
    protected $table = 'fin_entries';

    protected $fillable = [
        'gl_transaction_id',
        'account_id',
        'line_description',
        'debit_amount',
        'credit_amount'
    ];

    protected $casts = [
        'debit_amount' => 'decimal:' . (config('kompo-finance.decimal-scale') ?? 5),
        'credit_amount' => 'decimal:' . (config('kompo-finance.decimal-scale') ?? 5)
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Ensure only one of debit or credit has a value
            if ($model->debit_amount > 0 && $model->credit_amount > 0) {
                throw new \Exception('An entry cannot have both debit and credit amounts');
            }

            if ($model->debit_amount == 0 && $model->credit_amount == 0) {
                throw new \Exception('An entry must have either a debit or credit amount');
            }

            // Validate account exists and is active
            $account = GlAccount::where('account_id', $model->account_id)->first();
            if (!$account) {
                throw new \Exception("Account {$model->account_id} does not exist");
            }

            if (!$account->is_active) {
                throw new \Exception("Account {$model->account_id} is disabled");
            }

            // Check if manual entry is allowed for manual GL transactions
            $transaction = $model->glTransaction;
            if ($transaction && $transaction->transaction_type === GlTransaction::TYPE_MANUAL_GL) {
                if (!$account->allow_manual_entry) {
                    throw new \Exception("Account {$model->account_id} does not allow manual entries");
                }
            }
        });
    }

    /**
     * Get the GL transaction this entry belongs to
     */
    public function glTransaction(): BelongsTo
    {
        return $this->belongsTo(GlTransaction::class, 'gl_transaction_id');
    }

    /**
     * Get the GL account
     */
    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'account_id', 'account_id');
    }

    /**
     * Scope for debit entries
     */
    public function scopeDebits($query)
    {
        return $query->where('debit_amount', '>', 0);
    }

    /**
     * Scope for credit entries
     */
    public function scopeCredits($query)
    {
        return $query->where('credit_amount', '>', 0);
    }

    /**
     * Get the entry amount (positive for debits, negative for credits)
     */
    public function getAmount()
    {
        return $this->debit_amount - $this->credit_amount;
    }

    /**
     * Check if this is a debit entry
     */
    public function isDebit(): bool
    {
        return $this->debit_amount > 0;
    }

    /**
     * Check if this is a credit entry
     */
    public function isCredit(): bool
    {
        return $this->credit_amount > 0;
    }

    /**
     * Get entry type as string
     */
    public function getEntryType(): string
    {
        return $this->isDebit() ? 'Debit' : 'Credit';
    }

    /**
     * Get the absolute amount
     */
    public function getAbsoluteAmount()
    {
        return max($this->debit_amount, $this->credit_amount);
    }

    /**
     * Create a debit entry
     */
    public static function createDebit(
        int $glTransactionId,
        string $accountId,
        $amount,
        string $description = null
    ): static {
        return static::create([
            'gl_transaction_id' => $glTransactionId,
            'account_id' => $accountId,
            'debit_amount' => $amount,
            'credit_amount' => 0,
            'line_description' => $description
        ]);
    }

    /**
     * Create a credit entry
     */
    public static function createCredit(
        int $glTransactionId,
        string $accountId,
        $amount,
        string $description = null
    ): static {
        return static::create([
            'gl_transaction_id' => $glTransactionId,
            'account_id' => $accountId,
            'debit_amount' => 0,
            'credit_amount' => $amount,
            'line_description' => $description
        ]);
    }
}
