<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Budget;
use Condoedge\Finance\Models\BudgetDetail;
use Condoedge\Finance\Models\Fund;
use Kompo\Form;

class BudgetDetailsForm extends Form
{
    public $model = Budget::class;

    public $class = 'p-4 rounded-lg';

    protected $fundId;
    protected $fund;
    protected $group;

    protected $lastFiscalYear;

    protected $refresh = true;

    public function created()
    {
        $this->group = $this->store('group');

        $this->fundId = $this->store('fund_id');
        $this->fund = Fund::findOrFail($this->fundId);

        $this->lastFiscalYear = substr(carbon($this->model->fiscal_year_start)->addYears(-1), 0, 4);
    }

    public function render()
    {
        $fundAlreadyIncluded = $this->model->hasFundInvoiced($this->fundId);

        $formClass = $fundAlreadyIncluded ? BudgetDetailReadonlyForm::class : BudgetDetailForm::class;

        $multiForm = _MultiForm()->noLabel()->name('budgetDetails')
            ->relationScope(function($query){
                $query->where('fund_id', $this->fundId)
                    ->whereHas('account', fn($q) => $q->where('group', $this->group));
            })
            ->formClass($formClass, [
                'fund_id' => $this->fundId,
                'budget_id' => $this->model->id,
                'group' => $this->group,
            ])
            ->asTable([
                _Th('<br>'.__('finance.account')),
                _Th('<br>'.__('Amount'))->class('text-center'),
                _Th($this->lastFiscalYear.'<br>'.__('Real').' / '.__('Budget'))->class('text-right'),
                '',
                '',
            ]);

        return $fundAlreadyIncluded ? $multiForm->noAdding() : $multiForm->addLabel('finance.add-budget-item', 'icon-plus', 'mt-2 text-sm text-level3');
    }



    public function getBudgetDetailQuotesReadonly($budgetDetailId)
    {
        $budgetDetailQuotes = BudgetDetail::find($budgetDetailId)->budgetDetailQuotes()->with('unit')->get();
        $totalFractions = $budgetDetailQuotes->sum('fractions');

        return _Rows(
            $this->tableRow(
                'Unit',
                'Fraction',
                '%',
            ),
            !$budgetDetailQuotes->count() ? _Html('finance.no-custom-quotes-per-unit')->class('p-4 card-gray-100 mt-4') :
                _Rows(
                    $budgetDetailQuotes->map(fn($bdQuote) => $this->tableRow(
                        $bdQuote->unit->name.' ('.($bdQuote->unit->totalSharePct()*100).'%)',
                        round($bdQuote->fractions,4),
                        round($bdQuote->fractions / $totalFractions *100, 4) .'%',
                    ))
                )
        )->class('p-4');
    }

    protected function tableRow($col1, $col2, $col3)
    {
        return _FlexBetween(
            _Html($col1)->class('font-bold'),
            _FlexEnd4(
                _Html($col2)->class('w-16 text-right'),
                _Html($col3)->class('w-16 text-right'),
            )
        )->class('w-72 py-2 border-b border-gray-200');
    }

    public function deleteBudgetDetail($id)
    {
        BudgetDetail::findOrFail($id)->delete();
    }

    public function getBudgetDetailSettings($id)
    {
        return new BudgetDetailSettingsForm($id);
    }
}
