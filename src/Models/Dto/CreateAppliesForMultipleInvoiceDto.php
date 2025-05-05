<?php

namespace Condoedge\Finance\Models\Dto;

use Carbon\Carbon;
use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Models\ApplicableToInvoiceContract;
use WendellAdriel\ValidatedDTO\Attributes\Rules;
use WendellAdriel\ValidatedDTO\Casting\CarbonCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\ObjectCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\ValidatedDTO;
use stdClass;

class CreateAppliesForMultipleInvoiceDto extends ValidatedDTO
{
    use EmptyDefaults;

    #[Rules(['date_format:Y-m-d', 'required'])]
    public string|Carbon $apply_date;

    #[Rules(['required'])]
    public stdClass $applicable;

    #[Rules(['required'])]
    public int $applicable_type;

    // See below in rules method. The rules were to complex to be defined in line
    public array $amounts_to_apply;

    protected function casts(): array {
        return [
            'apply_date' => new CarbonCast,
            'applicable' => new ObjectCast,

            'applicable_type' => new IntegerCast,
        ];
    }
    
    protected function defaults(): array {
        return [];
    }

    protected function rules(): array
    {
        return [
            'amounts_to_apply' => ['array', 'required'],
            'amounts_to_apply.*.id' => ['numeric', 'required'],
            'amounts_to_apply.*.amount_applied' => ['numeric', 'required'],

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
        
        $applicableType = $this->dtoData['applicable_type'] ?? null;
        $applicable = $this->dtoData['applicable'] ?? null;
        $amountsToApply = $this->dtoData['amounts_to_apply'] ?? null;

        if (!is_null($amountsToApply)) {
            $invoicesIds = collect($amountsToApply)->pluck('id')->all();
            $invoices = InvoiceModel::whereIn('id', $invoicesIds)->get()->keyBy('id');

            foreach ($amountsToApply as $amountToApply) {
                if ($invoices->get($amountToApply['id'])->invoice_due_amount < $amountToApply['amount_applied']) {
                    $validator->errors()->add('amount_applied_to_' . $amountToApply['id'], __('translate.validation.custom.finance.invoice-amount-exceeded'));

                    $validator->errors()->add('amounts_to_apply', __('translate.validation.custom.finance.invoice-amount-exceeded'));
                }
            }
        }

        if (!is_null($applicableType) && !is_null($applicable)) {
            /**
             * @var ApplicableToInvoiceContract $applicableModel
             */
            $applicableModel = getFinanceMorphableModel($applicableType, $applicable['id']);

            if ($applicableModel->applicable_amount_left < collect($amountsToApply)->sum('amount_applied')) {
                $validator->errors()->add('applicable', __('translate.validation.custom.finance.applicable-amount-exceeded'));
            }
        }
    }


}