<?php

namespace Condoedge\Finance\Models\Dto\Payments;

use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Casts\SafeDecimalCast;
use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Rule\SafeDecimalRule;
use WendellAdriel\ValidatedDTO\Attributes\Rules;
use WendellAdriel\ValidatedDTO\Casting\CarbonCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\ObjectCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\Concerns\EmptyRules;
use WendellAdriel\ValidatedDTO\ValidatedDTO;
use stdClass;

class CreateApplyForInvoiceDto extends ValidatedDTO
{
    use EmptyRules, EmptyDefaults;

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
    protected function casts(): array {
        return [
            'apply_date' => new CarbonCast,
            'amount_applied' => new SafeDecimalCast,
            'invoice_id' => new IntegerCast,
            'applicable_type' => new IntegerCast,

            'applicable' => new ObjectCast,
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

        $applicable = $this->dtoData['applicable'] ?? null;
        $applicableType = $this->dtoData['applicable_type'] ?? null;

        $amountApplied = new SafeDecimal($this->dtoData['amount_applied'] ?? null);

        $invoiceId = $this->dtoData['invoice_id'] ?? null;

        $invoice = InvoiceModel::find($invoiceId);

        if ($invoice?->is_draft){
            $validator->errors()->add('invoice_id', __('translate.validation.custom.finance.invoice-draft'));
        }

        if ($amountApplied->equals(0)) {
            $validator->errors()->add('amount_applied', __('translate.validation.custom.finance.amount-applied-zero'));
        }

        if (!is_null($applicableType) && !is_null($applicable) && !is_null($amountApplied)) {
            /**
             * @var \Condoedge\Finance\Models\ApplicableToInvoiceContract $applicableModel
             */
            $applicableModel = getFinanceMorphableModel($applicableType, $applicable['id']);

            // Ensure the same sign with applicable amount left
            $amountApplied = $applicableModel->applicable_amount_left->multiply($amountApplied)->lessThan(0)
                ? $amountApplied->multiply(-1)
                : $amountApplied;

            // If we subtract the applied amount and it's greater than the original, it means it's a negative value. - if it is a invoice and + if it is a credit
            // payment_left * (payment_left - applied_amount) > 0
            $paymentLeftResult = $applicableModel->applicable_amount_left->subtract($amountApplied);

            if ($paymentLeftResult->multiply($applicableModel->applicable_amount_left)->lessThan(0)) {
                $validator->errors()->add('applicable', __('translate.validation.custom.finance.applicable-amount-exceeded'));
            }

            // You cannot apply a negative payment to a positive invoice
            // This means you're trying to apply a credit payment to an invoice
            // or a positive payment to a credit invoice, that would increase the amount due
            if($applicableModel->applicable_amount_left->multiply($invoice->invoice_type_id->signMultiplier())->lessThan(0)) {
                $validator->errors()->add('applicable', __('translate.validation.custom.finance.applicable-amount-negative'));
            }
        }

        if (!is_null($invoiceId) && !is_null($amountApplied) && ($invoice = InvoiceModel::find($invoiceId)) && !is_null($applicable)) {
            if ($invoice->customer_id != $applicable['customer_id']) {
                $validator->errors()->add('applicable', __('translate.validation.custom.finance.invoice-customer-mismatch'));
            }

            if ($invoice->abs_invoice_due_amount->lessThan($amountApplied)) {
                $validator->errors()->add('amount_applied', __('translate.validation.custom.finance.invoice-amount-exceeded'));
            }
        }
    }
}
    