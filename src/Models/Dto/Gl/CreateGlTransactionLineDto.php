<?php

namespace Condoedge\Finance\Models\Dto\Gl;

use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Casts\SafeDecimalCast;
use WendellAdriel\ValidatedDTO\ValidatedDTO;
use WendellAdriel\ValidatedDTO\Casting\FloatCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;

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
    public ?int $natural_account_id;
    public ?int $account_id;
    public ?string $line_description;
    public SafeDecimal $debit_amount;
    public SafeDecimal $credit_amount;
    
    /**
     * Defines the casts for the DTO properties.
     */
    public function casts(): array
    {
        return [
            'line_description' => new StringCast(),
            'account_id' => new IntegerCast(),
            'natural_account_id' => new IntegerCast(),
            'debit_amount' => new SafeDecimalCast(),
            'credit_amount' => new SafeDecimalCast(),
        ];
    }
    
    /**
     * Validation rules
     */
    public function rules(): array
    {
        return [
            'account_id' => 'required_without:natural_account_id|integer|exists:fin_gl_accounts,id',
            'natural_account_id' => 'required_without:account_id|integer|exists:fin_segment_values,id',
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
            'debit_amount' => new SafeDecimal(0.0),
            'credit_amount' => new SafeDecimal(0.0),
            'account_id' => 0,
            'natural_account_id' => 0,
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
                __('error-line-must-have-either-debit-or-credit')
            );
        }

        $accountId = $this->dtoData['account_id'] ?? null;
        $naturalAccountId = $this->dtoData['natural_account_id'] ?? null;
        $glTransactionType = $this->dtoData['gl_transaction_type'] ?? null;

        $glService = app(\Condoedge\Finance\Services\GlTransactionServiceInterface::class);

        if ($accountId && $glTransactionType) {
            try {
                $glService->validateAccountAbleToTransaction($accountId, $glTransactionType);
            } catch (\InvalidArgumentException $e) {
                $validator->errors()->add("account_id", $e->getMessage());
            }
        }

        if ($naturalAccountId && $glTransactionType) {
            try {
                $glService->validateNaturalAccountAbleToTransaction($naturalAccountId, $glTransactionType);
            } catch (\InvalidArgumentException $e) {
                $validator->errors()->add("natural_account_id", $e->getMessage());
            }
        }
    }
    
    /**
     * Validate that either debit or credit is specified, but not both
     */
    public function validateDebitCredit(): bool
    {
        $hasDebit = $this->dtoData['debit_amount'] > 0;
        $hasCredit = $this->dtoData['credit_amount'] > 0;

        // Either debit OR credit must be specified, but not both
        return ($hasDebit && !$hasCredit) || (!$hasDebit && $hasCredit);
    }
    
    /**
     * Get the amount (debit or credit)
     */
    public function getAmount(): float
    {
        return $this->dtoData['debit_amount'] > 0 ? $this->dtoData['debit_amount'] : $this->dtoData['credit_amount'];
    }
    
    /**
     * Check if this is a debit line
     */
    public function isDebit(): bool
    {
        return $this->dtoData['debit_amount'] > 0;
    }
    
    /**
     * Check if this is a credit line
     */
    public function isCredit(): bool
    {
        return $this->dtoData['credit_amount'] > 0;
    }
}
