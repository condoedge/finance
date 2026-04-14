<?php

namespace Condoedge\Finance\Models\Dto\Gl;

use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Enums\GlTransactionTypeEnum;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\SegmentValue;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\EnumCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

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

    public function rules(): array
    {
        return [
            'fiscal_date' => 'required|date',
            'gl_transaction_type' => 'required|in:' . collect(GlTransactionTypeEnum::cases())->pluck('value')->implode(','),
            'transaction_description' => 'required|string|max:500',
            'team_id' => 'required|integer|exists:teams,id',
            'customer_id' => 'nullable|integer|exists:fin_customers,id',
            'vendor_id' => 'nullable|integer',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required_without:lines.*.natural_account_id|integer|exists:fin_gl_accounts,id',
            'lines.*.natural_account_id' => 'required_without:lines.*.account_id|integer|exists:fin_segment_values,id',
            'lines.*.line_description' => 'nullable|string|max:255',
            'lines.*.debit_amount' => 'required|numeric|min:0',
            'lines.*.credit_amount' => 'required|numeric|min:0',
        ];
    }

    public function defaults(): array
    {
        return [
            'line_description' => null,
        ];
    }

    public function after($validator): void
    {
        $this->validateTransactionBalance($validator);
        $this->validateLines($validator);
        $this->validateTransactionParties($validator);
    }

    // -- Line validation -------------------------------------------------------

    protected function validateLines($validator): void
    {
        $lines = $this->dtoData['lines'] ?? [];
        $transactionType = $this->dtoData['gl_transaction_type'] ?? null;

        foreach ($lines as $index => $line) {
            $this->validateLineAmounts($validator, $line, $index);
            $this->validateLineAccount($validator, $line, $index, $transactionType);
            $this->validateLineNaturalAccount($validator, $line, $index, $transactionType);
        }
    }

    protected function validateLineAmounts($validator, $line, int $index): void
    {
        $debit = is_array($line) ? ($line['debit_amount'] ?? 0) : $line->debit_amount->toFloat();
        $credit = is_array($line) ? ($line['credit_amount'] ?? 0) : $line->credit_amount->toFloat();

        if (($debit > 0 && $credit > 0) || ($debit == 0 && $credit == 0)) {
            $validator->errors()->add("lines.{$index}", __('error-line-must-have-either-debit-or-credit'));
        }
    }

    protected function validateLineAccount($validator, $line, int $index, $transactionType): void
    {
        $accountId = is_array($line) ? ($line['account_id'] ?? null) : ($line->account_id ?? null);

        if (!$accountId || !$transactionType) {
            return;
        }

        $account = GlAccount::find($accountId);

        if (!$account || !$account->is_active) {
            $validator->errors()->add("lines.{$index}.account_id", __('error-account-inactive', ['account_id' => $accountId]));
        } elseif (!$account->allow_manual_entry && $transactionType === GlTransactionTypeEnum::MANUAL_GL) {
            $validator->errors()->add("lines.{$index}.account_id", __('error-account-not-allow-manual-entry', ['account_id' => $accountId]));
        }
    }

    protected function validateLineNaturalAccount($validator, $line, int $index, $transactionType): void
    {
        $naturalAccountId = is_array($line) ? ($line['natural_account_id'] ?? null) : ($line->natural_account_id ?? null);

        if (!$naturalAccountId || !$transactionType) {
            return;
        }

        $naturalAccount = SegmentValue::find($naturalAccountId);

        if (!$naturalAccount || !$naturalAccount->is_active) {
            $validator->errors()->add("lines.{$index}.natural_account_id", __('error-account-inactive', ['account_id' => $naturalAccountId]));
        } elseif (!$naturalAccount->allow_manual_entry && $transactionType === GlTransactionTypeEnum::MANUAL_GL) {
            $validator->errors()->add("lines.{$index}.natural_account_id", __('error-account-not-allow-manual-entry', ['account_id' => $naturalAccountId]));
        }
    }

    // -- Balance validation ----------------------------------------------------

    protected function validateTransactionBalance($validator): void
    {
        $totalDebits = $this->getTotalDebits();
        $totalCredits = $this->getTotalCredits();

        if (!$totalDebits->equals($totalCredits)) {
            $validator->errors()->add('lines', __('validation-with-values-transaction-must-balance', [
                'debits' => finance_currency($totalDebits),
                'credits' => finance_currency($totalCredits),
            ]));
        }
    }

    // -- Party validation ------------------------------------------------------

    protected function validateTransactionParties($validator): void
    {
        $transactionType = $this->dtoData['gl_transaction_type'] ?? null;

        if ($transactionType === GlTransactionTypeEnum::PAYABLE && empty($this->dtoData['vendor_id'] ?? null)) {
            $validator->errors()->add('vendor_id', __('error-vendor-required-for-ap-transactions'));
        }

        if ($transactionType === GlTransactionTypeEnum::RECEIVABLE && empty($this->dtoData['customer_id'] ?? null)) {
            $validator->errors()->add('customer_id', __('error-customer-required-for-ar-transactions'));
        }
    }

    // -- Helpers ---------------------------------------------------------------

    public function getTotalDebits(): SafeDecimal
    {
        return $this->sumLineField('debit_amount');
    }

    public function getTotalCredits(): SafeDecimal
    {
        return $this->sumLineField('credit_amount');
    }

    protected function sumLineField(string $field): SafeDecimal
    {
        $total = new SafeDecimal(0.0);

        foreach ($this->dtoData['lines'] ?? [] as $line) {
            $value = is_array($line) ? ($line[$field] ?? 0) : $line->$field;
            $total = $total->add(new SafeDecimal($value));
        }

        return $total;
    }

    public function getLinesDtos(): array
    {
        return collect($this->lines)->map(function ($line) {
            return $line instanceof CreateGlTransactionLineDto
                ? $line
                : new CreateGlTransactionLineDto($line);
        })->all();
    }

    public function isManualGlTransaction(): bool
    {
        return $this->gl_transaction_type === GlTransactionTypeEnum::MANUAL_GL;
    }
}
