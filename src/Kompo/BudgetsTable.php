<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Budget;
use Kompo\Table;

class BudgetsTable extends Table
{
    public $class = 'mb-6';

    public $containerClass = 'container';

    public function query()
    {
        return currentUnion()->budgets()->orderByDesc('fiscal_year_start');
    }

    public function headers()
    {
        return [
            _Th('Status'),
            _Th('Budget'),
            _Th('fiscal-year-start'),
            _Th(),
        ];
    }

    public function top()
    {
        return _FlexBetween(
            _PageTitle('finance.budget-overview'),
            _Link('finance.create-new-budget')->icon(_Sax('add',20))->button()
                ->get('budget-info.form')
                ->inModal(),
        )->class('mb-4');
    }

    public function render($budget)
    {
    	return _TableRow(
            _Html($budget->status_label)->class('vlTagOutlined text-sm'),
            _Rows(
                _Html($budget->name),
                _Html($budget->description)->class('text-gray-600 text-xs')
            ),
            _Html($budget->fiscal_year_start->format('d M Y')),
            _TripleDotsDropdown(
                $this->addStyleLink(
                    _Link('finance.edit-budget-info')->icon('icon-edit')
                        ->get('budget-info.form', [
                            'id' => $budget->id
                        ])->inModal()
                ),

                $this->addStyleLink(
                    _Link('finance.go-to-details')->icon('view-list')
                        ->href('budget.view', [
                            'id' => $budget->id
                        ])
                ),

                $this->addStyleLink(
                    _Link('finance.Duplicate')->icon('document-duplicate')
                        ->selfPost('duplicateBudget', [
                            'id' => $budget->id
                        ])->inModal()
                        ->browse()
                ),

                $this->addStyleLink(
                    _Link('finance.reset-invoices')->icon('refresh')
                        ->balloon('finance.reset-invoices-balloon', 'left')
                        ->selfPost('resetBudget', [
                            'id' => $budget->id
                        ])->inAlert()
                        ->browse()
                ),

                !auth()->user()->can('delete', $budget) ? null :
                    $this->addStyleLink(
                        _DeleteLink('Delete')->icon('icon-trash')->byKey($budget)
                    )
            )->alignRight()
    	)->class('cursor-pointer')
        ->href('budget.view', [
            'id' => $budget->id
        ])->forceTurbo();
    }

    public function duplicateBudget($budgetId)
    {
        $budget = Budget::with('budgetDetails')->findOrFail($budgetId);

        $newBudget = $budget->replicate();
        $newBudget->name = $budget->name.' ('.__('Copy').')';
        $newBudget->status = Budget::STATUS_DRAFT;
        $newBudget->save();

        $newBudget->budgetDetails()->saveMany($budget->budgetDetails->map(fn($bd) => $bd->replicate()));

        return new BudgetInfoForm($newBudget->id);

    }

    public function resetBudget($budgetId)
    {
        $budget = Budget::findOrFail($budgetId);

        $budget->resetInvoices();

        return __('finance.budget-invoices-reset');

    }

    protected function addStyleLink($komponent)
    {
        return $komponent->class('text-sm px-2 py-1 border-b border-gray-200 whitespace-nowrap block');
    }
}
