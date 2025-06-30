<?php

namespace Condoedge\Finance\Models\Dto\Gl;

use WendellAdriel\ValidatedDTO\ValidatedDTO;
use WendellAdriel\ValidatedDTO\Casting\FloatCast;

/**
 * Create GL Transaction Line DTO
 * 
 * Represents a single line item in a general ledger transaction.
 * Each line must have either a debit OR credit amount (but not both).
 * 
 * @property string $account_id The GL account this line applies to
 * @property string|null $line_description Optional description for this transaction line
 * @property float $debit_amount Debit amount (must be 0 if credit_amount > 0)
 * @property float $credit_amount Credit amount (must be 0 if debit_amount > 0)
 */
class CreateGlTransactionLineDto extends ValidatedDTO
{
    public string $account_id;
    public ?string $line_description = null;
    public float $debit_amount = 0.0;
    public float $credit_amount = 0.0;
    
    /**
     * Defines the casts for the DTO properties.
     */
    public function casts(): array
    {
        return [
            'debit_amount' => new FloatCast(),
            'credit_amount' => new FloatCast(),
        ];
    }
    
    /**
     * Validation rules
     */
    public function rules(): array
    {
        return [
            'account_id' => 'required|string|exists:fin_gl_accounts,account_id',
            'line_description' => 'nullable|string|max:255',
            'debit_amount' => 'required|numeric|min:0',
            'credit_amount' => 'required|numeric|min:0',
        ];
    }
    
    /**
     * Default values for DTO properties
     */
    public function defaults(): array
    {
        return [
            'line_description' => null,
            'debit_amount' => 0.0,
            'credit_amount' => 0.0,
        ];
    }
    
    /**
     * Additional validation after basic rules
     */
    public function after($validator): void
    {
        // Validate that either debit or credit is specified, but not both
        if (!$this->validateDebitCredit()) {
            $validator->errors()->add(
                'amount', 
                __('translate.line-must-have-either-debit-or-credit')
            );
        }
    }
    
    /**
     * Validate that either debit or credit is specified, but not both
     */
    public function validateDebitCredit(): bool
    {
        $hasDebit = $this->debit_amount > 0;
        $hasCredit = $this->credit_amount > 0;
        
        // Either debit OR credit must be specified, but not both
        return ($hasDebit && !$hasCredit) || (!$hasDebit && $hasCredit);
    }
    
    /**
     * Get the amount (debit or credit)
     */
    public function getAmount(): float
    {
        return $this->debit_amount > 0 ? $this->debit_amount : $this->credit_amount;
    }
    
    /**
     * Check if this is a debit line
     */
    public function isDebit(): bool
    {
        return $this->debit_amount > 0;
    }
    
    /**
     * Check if this is a credit line
     */
    public function isCredit(): bool
    {
        return $this->credit_amount > 0;
    }
}
