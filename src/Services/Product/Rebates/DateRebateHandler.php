<?php

namespace Condoedge\Finance\Services\Product\Rebates;

use Condoedge\Finance\Models\Product;
use Condoedge\Finance\Models\Rebate;

class DateRebateHandler extends AbstractRebateHandler 
{
    function shouldApplyRebate(Product $product, Rebate $rebate): bool
    {
        $currentDate = $this->context['rebate_date'] ?? null;

        // Here we don't use default to now() because we want to control if the rebate will be applied in that context or not
        // For example when we create the template of the invoice we don't want to consider that time to apply or not the discount
        // We need the concrete case like inscriptions when we now that they are creating the invoice for a real client that is supposed to pay
        if (!$currentDate) {
            return false;
        }

        $rebateParams = $rebate->rebate_logic_parameters;

        return $currentDate->between($rebateParams['start_date'], $rebateParams['end_date']);
    }

    function getHandlerLabel(): string
    {
        return __('translate.date');
    }

    function getHandlerParamsFields()
    {
        return _Rows(
            _Date('translate.start-date')->name('rebate_logic_parameters[start_date]')->required(),
            _Date('translate.end-date')->name('rebate_logic_parameters[end_date]')->required()
        );  
    }

    function getHandlerParamsRules(): array
    {
        return [
            'rebate_logic_parameters.start_date' => ['required', 'date'],
            'rebate_logic_parameters.end_date' => ['required', 'date', 'after_or_equal:rebate_logic_parameters.start_date'],
        ];
    }

    public function getHandlerParamsLabel($params): string
    {
        return __('translate.date-range', $params);
    }
}
