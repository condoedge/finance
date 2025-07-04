<?php

namespace Condoedge\Finance\Models\Dto\Payments;

use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Models\ApplicableToInvoiceContract;
use Condoedge\Finance\Rule\SafeDecimalRule;
use stdClass;
use WendellAdriel\ValidatedDTO\Attributes\Rules;
use WendellAdriel\ValidatedDTO\Casting\CarbonCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\ObjectCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\Concerns\EmptyRules;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class CreateAppliesForMultipleInvoiceDto extends ValidatedDTO
{
    use EmptyRules;
    use EmptyDefaults;

    #[Rules(['date', 'required'])]
    public string|\Carbon\Carbon $apply_date;

    #[Rules(['required'])]
    public stdClass $applicable;

    #[Rules(['required'])]
    public int $applicable_type;

    // See below in rules method. The rules were to complex to be defined in line
    public array $amounts_to_apply;

    protected function casts(): array
    {
        return [
            'apply_date' => new CarbonCast(),
            'applicable' => new ObjectCast(),

            'applicable_type' => new IntegerCast(),
        ];
    }

    protected function defaults(): array
    {
        return [];
    }

    protected function rules(): array
    {
        return [
            'amounts_to_apply' => ['array', 'required'],
            'amounts_to_apply.*.id' => ['numeric', 'required'],
            'amounts_to_apply.*.amount_applied' => [new SafeDecimalRule(true), 'required'],

            'applicable.id' => ['numeric', 'required'],
        ];
    }

    public function validate(): void
    {
        parent::validate();
    }

    public function after(\Illuminate\Validation\Validator $validator): void
    {
        parent::after($validator);

        $this->validateInvoicesState($validator);
        $this->validateIndividualAmounts($validator);
        $this->validateTotalApplicableAmount($validator);
    }

    /**
     * Validate that all invoices exist and are not in draft state
     */
    protected function validateInvoicesState(\Illuminate\Validation\Validator $validator): void
    {
        $amountsToApply = $this->dtoData['amounts_to_apply'] ?? null;

        if (!is_null($amountsToApply)) {
            $invoicesIds = collect($amountsToApply)->pluck('id')->all();
            $invoices = InvoiceModel::whereIn('id', $invoicesIds)->get()->keyBy('id');

            foreach ($amountsToApply as $amountToApply) {
                if (!isset($amountToApply['id'])) {
                    continue; // Will be validated by rules
                }

                $invoice = $invoices->get($amountToApply['id']);
                if ($invoice && $invoice->is_draft) {
                    $validator->errors()->add('amount_applied_to_' . $amountToApply['id'], __('validation-custom-finance-invoice-draft'));
                    $validator->errors()->add('amounts_to_apply', __('validation-custom-finance-invoice-draft'));
                }
            }
        }
    }

    /**
     * Validate individual amount applications don't exceed invoice due amounts
     */
    protected function validateIndividualAmounts(\Illuminate\Validation\Validator $validator): void
    {
        $amountsToApply = $this->dtoData['amounts_to_apply'] ?? null;

        if (!is_null($amountsToApply)) {
            $invoicesIds = collect($amountsToApply)->pluck('id')->all();
            $invoices = InvoiceModel::whereIn('id', $invoicesIds)->get()->keyBy('id');

            foreach ($amountsToApply as $amountToApply) {
                if (!isset($amountToApply['id']) || !isset($amountToApply['amount_applied'])) {
                    continue; // Will be validated by rules
                }

                $invoice = $invoices->get($amountToApply['id']);
                $amount = new SafeDecimal($amountToApply['amount_applied']);

                if ($invoice && $invoice->abs_invoice_due_amount->lessThan($amount)) {
                    $validator->errors()->add('amount_applied_to_' . $amountToApply['id'], __('validation-custom-finance-invoice-amount-exceeded'));
                    $validator->errors()->add('amounts_to_apply', __('validation-custom-finance-invoice-amount-exceeded'));
                }
            }
        }
    }

    /**
     * Validate total amount to apply doesn't exceed applicable amount left
     */
    protected function validateTotalApplicableAmount(\Illuminate\Validation\Validator $validator): void
    {
        $applicableType = $this->dtoData['applicable_type'] ?? null;
        $applicable = $this->dtoData['applicable'] ?? null;
        $amountsToApply = $this->dtoData['amounts_to_apply'] ?? null;

        if (!is_null($applicableType) && !is_null($applicable) && !is_null($amountsToApply)) {
            /**
             * @var ApplicableToInvoiceContract $applicableModel
             */
            $applicableModel = getFinanceMorphableModel($applicableType, $applicable['id']);

            $totalAmount = collect($amountsToApply)->reduce(function ($carry, $amountToApply) {
                return $carry->add(new SafeDecimal($amountToApply['amount_applied'] ?? '0.00'));
            }, new SafeDecimal('0.00'));

            if ($applicableModel->abs_applicable_amount_left->lessThan($totalAmount)) {
                $validator->errors()->add('applicable', __('validation-custom-finance-applicable-amount-exceeded'));
            }
        }
    }


}
