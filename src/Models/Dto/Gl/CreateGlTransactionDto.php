<?php

namespace Condoedge\Finance\Models\Dto\Gl;

use WendellAdriel\ValidatedDTO\ValidatedDTO;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;

/**
 * Create GL Transaction DTO
 * 
 * Used to create new general ledger transactions with multiple line items.
 * Ensures double-entry bookkeeping by requiring balanced debits and credits.
 * 
 * @property string $fiscal_date The fiscal date for this transaction
 * @property int $gl_transaction_type Type of GL transaction (1=Manual, 2=Bank, 3=AR, 4=AP)
 * @property string $transaction_description Description of the transaction
 * @property int $team_id The team/company this transaction belongs to
 * @property string|null $originating_module_transaction_id Reference to originating module transaction
 * @property int|null $customer_id Associated customer (for AR transactions)
 * @property int|null $vendor_id Associated vendor (for AP transactions)
 * @property array $lines Array of transaction lines (CreateGlTransactionLineDto objects)
 */
class CreateGlTransactionDto extends ValidatedDTO
{
    public string $fiscal_date;
    public int $gl_transaction_type;
    public string $transaction_description;
    public int $team_id;
    public ?string $originating_module_transaction_id = null;
    public ?int $customer_id = null;
    public ?int $vendor_id = null;
    public array $lines = [];
    
    /**
     * Defines the casts for the DTO properties.
     */
    protected function casts(): array
    {
        return [
            'gl_transaction_type' => new IntegerCast(),
            'team_id' => new IntegerCast(),
            'customer_id' => new IntegerCast(),
            'vendor_id' => new IntegerCast(),
        ];
    }
      /**
     * Validation rules
     */
    protected function rules(): array
    {
        return [
            'fiscal_date' => 'required|date',
            'gl_transaction_type' => 'required|integer|in:1,2,3,4', // 1=Manual, 2=Bank, 3=AR, 4=AP
            'transaction_description' => 'required|string|max:500',
            'team_id' => 'required|integer|exists:teams,id',
            'originating_module_transaction_id' => 'nullable|string|max:50',
            'customer_id' => 'nullable|integer|exists:fin_customers,id',
            'vendor_id' => 'nullable|integer',
            'lines' => 'required|array|min:2', // At least 2 lines required for double-entry
        ];
    }
    
    /**
     * Default values
     */
    protected function defaults(): array
    {
        return [];
    }
    
    /**
     * Validate that the transaction balances (debits = credits)
     */
    public function validateBalance(): bool
    {
        $totalDebits = 0;
        $totalCredits = 0;
        
        foreach ($this->lines as $lineData) {
            if (is_array($lineData)) {
                $totalDebits += $lineData['debit_amount'] ?? 0;
                $totalCredits += $lineData['credit_amount'] ?? 0;
            } elseif ($lineData instanceof CreateGlTransactionLineDto) {
                $totalDebits += $lineData->debit_amount;
                $totalCredits += $lineData->credit_amount;
            }
        }
        
        // Allow for small rounding differences
        return abs($totalDebits - $totalCredits) < 0.01;
    }
    
    /**
     * Get total debit amount
     */
    public function getTotalDebits(): float
    {
        $total = 0;
        
        foreach ($this->lines as $lineData) {
            if (is_array($lineData)) {
                $total += $lineData['debit_amount'] ?? 0;
            } elseif ($lineData instanceof CreateGlTransactionLineDto) {
                $total += $lineData->debit_amount;
            }
        }
        
        return $total;
    }
    
    /**
     * Get total credit amount
     */
    public function getTotalCredits(): float
    {
        $total = 0;
        
        foreach ($this->lines as $lineData) {
            if (is_array($lineData)) {
                $total += $lineData['credit_amount'] ?? 0;
            } elseif ($lineData instanceof CreateGlTransactionLineDto) {
                $total += $lineData->credit_amount;
            }
        }
        
        return $total;
    }
      /**
     * Convert lines to DTOs if they're arrays
     */
    public function getLinesDtos(): array
    {
        $lineDtos = [];
        
        foreach ($this->lines as $lineData) {
            if (is_array($lineData)) {
                $lineDtos[] = new CreateGlTransactionLineDto($lineData);
            } elseif ($lineData instanceof CreateGlTransactionLineDto) {
                $lineDtos[] = $lineData;
            }
        }
        
        return $lineDtos;
    }
    
    /**
     * Check if this is a manual GL transaction
     */
    public function isManualGlTransaction(): bool
    {
        return $this->gl_transaction_type === 1;
    }
}
