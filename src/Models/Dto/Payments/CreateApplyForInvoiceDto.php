<?php

namespace Condoedge\Finance\Models\Dto\Payments;

use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Casts\SafeDecimalCast;
use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Rule\SafeDecimalRule;
use stdClass;
use WendellAdriel\ValidatedDTO\Attributes\Rules;
use WendellAdriel\ValidatedDTO\Casting\CarbonCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\ObjectCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\Concerns\EmptyRules;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class CreateApplyForInvoiceDto extends ValidatedDTO
{
    use EmptyRules;
    use EmptyDefaults;

    #[Rules(['date', 'required'])]
    public string|\Carbon\Carbon $apply_date;

    #[Rules(['required'])]
    public stdClass $applicable;

    #[Rules(['required'])]
    public int $applicable_type;

    #[Rules([new SafeDecimalRule(true), 'required'])]
    public SafeDecimal $amount_applied;

    #[Rules(['numeric', 'required', 'exists:fin_invoices,id'])]
    public $invoice_id;


    /**
     * @inheritDoc
     */
    protected function casts(): array
    {
        return [
            'apply_date' => new CarbonCast(),
            'amount_applied' => new SafeDecimalCast(),
            'invoice_id' => new IntegerCast(),
            'applicable_type' => new IntegerCast(),

            'applicable' => new ObjectCast(),
        ];
    }

    protected function rules(): array
    {
        return [
            'applicable.id' => ['numeric', 'required'],
        ];
    }

    public function after(\Illuminate\Validation\Validator $validator): void
    {
        parent::after($validator);

        $this->validateInvoiceState($validator);
        $this->validateAmountApplied($validator);
        $this->validateApplicableAmounts($validator);
        $this->validateCustomerMatching($validator);
    }

    /**
     * Validate invoice state (not draft, exists)
     */
    protected function validateInvoiceState(\Illuminate\Validation\Validator $validator): void
    {
        $invoiceId = $this->dtoData['invoice_id'] ?? null;
        $invoice = InvoiceModel::find($invoiceId);

        if ($invoice?->is_draft) {
            $validator->errors()->add('invoice_id', __('validation-custom-finance-invoice-draft'));
        }
    }

    /**
     * Validate amount applied is not zero
     */
    protected function validateAmountApplied(\Illuminate\Validation\Validator $validator): void
    {
        $amountApplied = new SafeDecimal($this->dtoData['amount_applied'] ?? null);

        if ($amountApplied->equals(0)) {
            $validator->errors()->add('amount_applied', __('validation-custom-finance-amount-applied-zero'));
        }
    }

    /**
     * Validate applicable amounts and sign consistency
     */
    protected function validateApplicableAmounts(\Illuminate\Validation\Validator $validator): void
    {
        $applicable = $this->dtoData['applicable'] ?? null;
        $applicableType = $this->dtoData['applicable_type'] ?? null;
        $amountApplied = new SafeDecimal($this->dtoData['amount_applied'] ?? null);
        $invoiceId = $this->dtoData['invoice_id'] ?? null;
        $invoice = InvoiceModel::find($invoiceId);

        if (!is_null($applicableType) && !is_null($applicable) && !is_null($amountApplied)) {
            /**
             * @var \Condoedge\Finance\Models\ApplicableToInvoiceContract $applicableModel
             */
            $applicableModel = getFinanceMorphableModel($applicableType, $applicable['id']);

            // Ensure the same sign with applicable amount left
            $amountApplied = $applicableModel->applicable_amount_left->multiply($amountApplied)->lessThan(0)
                ? $amountApplied->multiply(-1)
                : $amountApplied;

            // Validate amount doesn't exceed available
            $paymentLeftResult = $applicableModel->applicable_amount_left->subtract($amountApplied);
            if ($paymentLeftResult->multiply($applicableModel->applicable_amount_left)->lessThan(0)) {
                $validator->errors()->add('applicable', __('validation-custom-finance-applicable-amount-exceeded'));
            }

            // Validate sign consistency (cannot apply negative payment to positive invoice)
            if ($applicableModel->applicable_amount_left->multiply($invoice->invoice_type_id->signMultiplier())->lessThan(0)) {
                $validator->errors()->add('applicable', __('validation-custom-finance-applicable-amount-negative'));
            }
        }
    }

    /**
     * Validate customer matching and invoice amounts
     */
    protected function validateCustomerMatching(\Illuminate\Validation\Validator $validator): void
    {
        $invoiceId = $this->dtoData['invoice_id'] ?? null;
        $amountApplied = new SafeDecimal($this->dtoData['amount_applied'] ?? null);
        $applicable = $this->dtoData['applicable'] ?? null;
        $invoice = InvoiceModel::find($invoiceId);

        if (!is_null($invoiceId) && !is_null($amountApplied) && $invoice && !is_null($applicable)) {
            // Validate customer matches
            if ($invoice->customer_id != $applicable['customer_id']) {
                $validator->errors()->add('applicable', __('validation-custom-finance-invoice-customer-mismatch'));
            }

            // Validate amount doesn't exceed invoice due
            if ($invoice->abs_invoice_due_amount->lessThan($amountApplied)) {
                $validator->errors()->add('amount_applied', __('validation-custom-finance-invoice-amount-exceeded'));
            }
        }
    }
}
