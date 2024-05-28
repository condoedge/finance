<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Budget;
use Condoedge\Finance\Models\Fund;
use Condoedge\Finance\Models\FundDate;
use Kompo\Form;

class BudgetFundDetails extends Form
{
    public $model = Fund::class;

    public $class = 'dashboard-card';

    protected $budgetId;
    protected $budget;
    protected $fundPanelId;

    protected $detailsTitleClass = 'bg-gray-100 text-level3 py-2 px-4';

    public function created()
    {
        $this->budgetId = $this->store('budget_id');
        $this->budget = Budget::findOrFail($this->budgetId);

        $this->fundPanelId = 'fund-panel-'.$this->model->id;
    }

    public function render()
    {
        $toggleId = uniqid();

        $fundHasDates = $this->model->fundDates()->count();

        $fundRevenue = $this->budget->getRevenue(null, $this->model);
        $fundExpenses = -1 * ($this->budget->getAmount(null, $this->model) - $fundRevenue);

        $fundInvoiced = $this->budget->hasFundInvoiced($this->model->id);
        $fundEmpty = !$this->budget->budgetDetails()->where('fund_id', $this->model->id)->count();

        return [
            _Rows(
                _FlexBetween(
                    _Flex4(
                        $this->budget->isApproved() ?
                            _Html()
                                ->icon($fundInvoiced ? 'check-circle' : ($fundEmpty ? '<span>-</span>' : 'exclamation-circle'),'text-3xl')
                                ->class($fundInvoiced ? 'text-positive' : ($fundEmpty ? 'text-gray-600' : 'text-danger'))
                                ->balloon($fundInvoiced ? __('finance.included-in-contributions') : __('finance.not-included-in-contributions'), 'right') :
                            null,
                        _Html($this->model->name)->rIcon('icon-down')
                            ->class('font-semibold text-level3'),
                    ),
                    _FlexEnd(
                        _Currency($fundRevenue)->class('font-semibold text-level3 whitespace-nowrap w-28')
                            ->class('fund-total-revenue'),
                        _Sax('money-recive')->class('text-gray-500 ml-2 mr-8'),
                        _Currency($fundExpenses)->class('font-semibold text-level3 whitespace-nowrap w-28')
                            ->class('fund-total-expense'),
                        _Sax('money-send')->class('text-gray-500 ml-2 mr-8'),
                        _Currency($fundRevenue - $fundExpenses)->class('font-semibold text-level3 whitespace-nowrap w-28')
                            ->class('fund-total-provision'),
                        _Html('Net')->class('text-gray-500 ml-2 text-sm font-medium'),
                    )->class('text-right')
                )->class('px-4 py-4 cursor-pointer')
                ->toggleId($toggleId)
                ->toggleClass('border-2 border-b-0 border-level3 border-opacity-75 rounded-t-xl rounded-b-none'),
                _Rows(
                    _Html('Expenses')->class($this->detailsTitleClass)->class('text-sm font-bold'),
                    new BudgetDetailsForm($this->budgetId, [
                        'fund_id' => $this->model->id,
                        'group' => GlAccount::GROUP_EXPENSE,
                    ]),
                    _FlexBetween(
                        _Html('Income')->class('font-bold'),
                        _FlexEnd4(
                            _Html(__('legend').':')->class('text-gray-300 font-medium'),
                            _Flex(
                                _Svg('ban')->class('w-4 text-red-700'),
                                _Html('finance.excluded-from-contributions')->class('ml-1'),
                            ),
                            _Flex(
                                _Html('$')->class('w-4 text-gray-600 text-center text-base'),
                                _Html('finance.included-in-contributions')->class('ml-1'),
                            ),
                        ),
                    )->class($this->detailsTitleClass)->class('text-sm'),
                    new BudgetDetailsForm($this->budgetId, [
                        'fund_id' => $this->model->id,
                        'group' => GlAccount::GROUP_INCOME,
                    ]),
                    $fundInvoiced ?

                        (!$fundHasDates ? null : $this->getFundDatesForm(true)) :

                        _Rows(
                            _Toggle('finance.toggle-to-change-fund')
                                ->name('monthly_allocation', false)
                                ->value($fundHasDates ? 1 : 0)
                                ->selfPost('loadFundDates')
                                ->inPanel($this->fundPanelId),
                            _Panel(
                                $fundHasDates ?
                                    $this->getFundDatesForm() :
                                    null
                            )->id($this->fundPanelId),
                        )->class('px-4'),
                )->id($toggleId)
                ->class('border-2 border-t-0 border-level1 rounded-b-2xl')
            )->class($fundInvoiced ? '' : 'fund-details-card')
            ->attr(['data-fund-id' => $this->model->id]),
        ];
    }

    public function loadFundDates($toggleValue)
    {
        if (!$toggleValue) {
            //FundDate::where('fund_id', $this->model->id)->delete(); //TODO: check with Benoit
            return;
        }

        return $this->getFundDatesForm();
    }

    protected function getFundDatesForm($readonly = false)
    {
        return new FundDatesForm([
            'fund_id' => $this->model->id,
            'budget_id' => $this->budgetId,
            'is_readonly' => $readonly,
        ]);
    }

}
