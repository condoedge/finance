<?php

namespace Condoedge\Finance\Models\Dto\Payments;

use Condoedge\Finance\Facades\InvoiceModel;
use WendellAdriel\ValidatedDTO\Attributes\Rules;
use WendellAdriel\ValidatedDTO\Casting\CarbonCast;
use WendellAdriel\ValidatedDTO\Casting\FloatCast;
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

    #[Rules(['numeric', 'required'])]
    public $amount_applied;

    #[Rules(['numeric', 'required', 'exists:fin_invoices,id'])]
    public $invoice_id;
    

    /**
     * @inheritDoc
     */
    protected function casts(): array {
        return [
            'apply_date' => new CarbonCast,
            'amount_applied' => new FloatCast,
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

        $amountApplied = $this->dtoData['amount_applied'] ?? null;

        $invoiceId = $this->dtoData['invoice_id'] ?? null;

        if (!is_null($applicableType) && !is_null($applicable) && !is_null($amountApplied)) {
            /**
             * @var \Condoedge\Finance\Models\ApplicableToInvoiceContract $applicableModel
             */
            $applicableModel = getFinanceMorphableModel($applicableType, $applicable['id']);

            if ($applicableModel->applicable_amount_left < $amountApplied) {
                $validator->errors()->add('applicable', __('translate.validation.custom.finance.applicable-amount-exceeded'));
            }
        }

        if (!is_null($invoiceId) && !is_null($amountApplied) && ($invoice = InvoiceModel::find($invoiceId)) && !is_null($applicable)) {
            if ($invoice->customer_id != $applicable['customer_id']) {
                $validator->errors()->add('applicable', __('translate.validation.custom.finance.invoice-customer-mismatch'));
            }

            if ($invoice->invoice_due_amount < $amountApplied) {
                $validator->errors()->add('amount_applied', __('translate.validation.custom.finance.invoice-amount-exceeded'));
            }
        }
    }
}
    