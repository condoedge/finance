<?php

namespace Condoedge\Finance\Models\Dto\Gl;

use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Enums\GlTransactionTypeEnum;
use WendellAdriel\ValidatedDTO\ValidatedDTO;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\DTOCast;
use WendellAdriel\ValidatedDTO\Casting\EnumCast;

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
 * @property int|null $customer_id Associated customer (for AR transactions)
 * @property int|null $vendor_id Associated vendor (for AP transactions)
 * @property array $lines Array of transaction lines (CreateGlTransactionLineDto objects)
 */
class CreateGlTransactionDto extends ValidatedDTO
{
    public string $fiscal_date;
    public GlTransactionTypeEnum $gl_transaction_type;
    public string $transaction_description;
    public int $team_id;
    public ?int $customer_id = null;
    public ?int $vendor_id = null;
    public array $lines;
    
    /**
     * Defines the casts for the DTO properties.
     */
    public function casts(): array
    {
        return [
            'gl_transaction_type' => new EnumCast(GlTransactionTypeEnum::class),
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
            'gl_transaction_type' => 'required|in:' . collect(GlTransactionTypeEnum::cases())->pluck('value')->implode(','),
            'transaction_description' => 'required|string|max:500',
            'team_id' => 'required|integer|exists:teams,id',
            'customer_id' => 'nullable|integer|exists:fin_customers,id',
            'vendor_id' => 'nullable|integer',
            'lines' => 'required|array|min:2', // At least 2 lines required for double-entry
            'lines.*.account_id' => 'required_without:lines.*.natural_account_id|string|exists:fin_gl_accounts,account_id',
            'lines.*.natural_account_id' => 'required_without:lines.*.account_id|integer|exists:fin_segment_values,id',
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
            'line_description' => null,
        ];
    }
    
    /**
     * Additional validation after basic rules
     */
    public function after($validator): void
    {
        $glTransactionType = $this->dtoData['gl_transaction_type'] ?? null;
        $lines = $this->dtoData['lines'] ?? [];
        $vendorId = $this->dtoData['vendor_id'] ?? null;
        $customerId = $this->dtoData['customer_id'] ?? null;

        // Validate that transaction is balanced
        if (!$this->validateBalance()) {
            $validator->errors()->add(
                'lines', 
                __('validation-with-values-transaction-must-balance', [
                    'debits' => finance_currency($this->getTotalDebits()),
                    'credits' => finance_currency($this->getTotalCredits())
                ])
            );
        }
        
        // Validate each line has either debit or credit, not both
        foreach ($lines as $index => $line) {
            $debit = is_array($line) ? ($line['debit_amount'] ?? 0) : $line->debit_amount;
            $credit = is_array($line) ? ($line['credit_amount'] ?? 0) : $line->credit_amount;
            
            if (($debit > 0 && $credit > 0) || ($debit == 0 && $credit == 0)) {
                $validator->errors()->add(
                    "lines.{$index}", 
                    __('error-line-must-have-either-debit-or-credit')
                );
            }
        }
        
        // Validate vendor_id is required for AP transactions
        if ($glTransactionType === GlTransactionTypeEnum::PAYABLE && empty($vendorId)) {
            $validator->errors()->add('vendor_id', __('error-vendor-required-for-ap-transactions'));
        }
        
        // Validate customer_id is required for AR transactions
        if ($glTransactionType === GlTransactionTypeEnum::RECEIVABLE && empty($customerId)) {
            $validator->errors()->add('customer_id', __('error-customer-required-for-ar-transactions'));
        }
    }
    
    /**
     * Validate that the transaction balances (debits = credits)
     */
    public function validateBalance(): bool
    {
        $totalDebits = new SafeDecimal($this->getTotalDebits());
        $totalCredits = new SafeDecimal($this->getTotalCredits());
        
        // Allow for small rounding differences
        return $totalDebits->equals($totalCredits);
    }
    
    /**
     * Get total debit amount
     */
    public function getTotalDebits(): SafeDecimal
    {
        $total = new SafeDecimal(0.0);
        $lines = $this->dtoData['lines'] ?? [];
        
        foreach ($lines as $lineData) {
            if (is_array($lineData)) {
                $total = $total->add(new SafeDecimal($lineData['debit_amount'] ?? 0));
            } elseif ($lineData instanceof CreateGlTransactionLineDto) {
                $total = $total->add($lineData->debit_amount);
            }
        }
        
        return $total;
    }
    
    /**
     * Get total credit amount
     */
    public function getTotalCredits(): SafeDecimal
    {
        $total = new SafeDecimal(0.0);
        $lines = $this->dtoData['lines'] ?? [];
        
        foreach ($lines as $lineData) {
            if (is_array($lineData)) {
                $total = $total->add(new SafeDecimal($lineData['credit_amount'] ?? 0));
            } elseif ($lineData instanceof CreateGlTransactionLineDto) {
                $total = $total->add($lineData->credit_amount);
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
