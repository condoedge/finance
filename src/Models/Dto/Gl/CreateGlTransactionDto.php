<?php

namespace Condoedge\Finance\Models\Dto\Gl;

use WendellAdriel\ValidatedDTO\ValidatedDTO;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\DTOCast;

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
    public function casts(): array
    {
        return [
            'gl_transaction_type' => new IntegerCast(),
            'team_id' => new IntegerCast(),
            'customer_id' => new IntegerCast(),
            'vendor_id' => new IntegerCast(),
            'lines' => new ArrayCast(),
        ];
    }
    
    /**
     * Validation rules
     */
    public function rules(): array
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
            'lines.*.account_id' => 'required_without:natural_account_id|string|exists:fin_gl_accounts,account_id',
            'lines.*.natural_account_id' => 'required_without:account_id|integer|exists:fin_segment_values,id',
            'lines.*.line_description' => 'nullable|string|max:255',
            'lines.*.debit_amount' => 'required|numeric|min:0',
            'lines.*.credit_amount' => 'required|numeric|min:0',
        ];
    }
    
    /**
     * Default values
     */
    public function defaults(): array
    {
        return [
            'lines' => [],
        ];
    }
    
    /**
     * Additional validation after basic rules
     */
    public function after($validator): void
    {
        // Validate that transaction is balanced
        if (!$this->validateBalance()) {
            $validator->errors()->add(
                'lines', 
                __('translate.transaction-must-balance', [
                    'debits' => number_format($this->getTotalDebits(), 2),
                    'credits' => number_format($this->getTotalCredits(), 2)
                ])
            );
        }
        
        // Validate each line has either debit or credit, not both
        foreach ($this->lines as $index => $line) {
            $debit = is_array($line) ? ($line['debit_amount'] ?? 0) : $line->debit_amount;
            $credit = is_array($line) ? ($line['credit_amount'] ?? 0) : $line->credit_amount;
            
            if (($debit > 0 && $credit > 0) || ($debit == 0 && $credit == 0)) {
                $validator->errors()->add(
                    "lines.{$index}", 
                    __('translate.line-must-have-either-debit-or-credit')
                );
            }
        }
        
        // Validate vendor_id is required for AP transactions
        if ($this->gl_transaction_type === 4 && empty($this->vendor_id)) {
            $validator->errors()->add('vendor_id', __('translate.vendor-required-for-ap-transactions'));
        }
        
        // Validate customer_id is required for AR transactions
        if ($this->gl_transaction_type === 3 && empty($this->customer_id)) {
            $validator->errors()->add('customer_id', __('translate.customer-required-for-ar-transactions'));
        }
    }
    
    /**
     * Validate that the transaction balances (debits = credits)
     */
    public function validateBalance(): bool
    {
        $totalDebits = $this->getTotalDebits();
        $totalCredits = $this->getTotalCredits();
        
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
