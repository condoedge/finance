<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\AccountBalance;
use App\View\Form;

class FinanceInitialSetupView extends Form
{
	public function render()
	{
		return [
			_FlexBetween(
				_PageTitle('finance.accounting-setup'),
				_Button('finance.all-steps-completed')
					->selfPost('approveSetup')
					->onSuccess(fn($e) => $e->redirect())
					->onError(fn($e) => $e->inAlert('icon-times', 'vlAlertError'))
			)->class('mb-6'),
			$this->section(1, new FiscalYearForm(currentUnion()->id)),
			$this->section(2, new AutoPaymentsPpaForm(currentUnion()->id)),
			$this->section(3, new FundsTable()),
			$this->section(4, new ChartOfAccountsBalances()),
		];
	}

	public function approveSetup()
	{
		$union = currentUnion(); //for the save phase otherwise we get the cache...

		/* DISABLED for flexibility and calculation to review...*/
		if (abs(
			AccountBalance::initialBalancesQuery($union->balance_date, $union)->selectRaw('SUM(credit_balance - debit_balance) as verif')->first()->verif
		) > 0.01) {
			abort(403, __('finance.error-sum-not-equal-zero'));
		}

		if (!$union->balance_date) {
			abort(403, __('finance.error-no-balance-start-date'));
		}

		if (!$union->fiscal_year_start_date) {
			abort(403, __('finance.error-no-fiscal-year-start'));
		}

		$union->finance_setup = 1;
		$union->save();

		$union->createDefaultBankIfNone();

		return redirect()->route('chart-of-accounts');
	}

	protected function section($number, $elements)
	{
		return _Flex(
			!$number ? null :
				_Html($number.'.')->class('text-gray-300 shrink-0 mr-4')->style('font-size:4vw;width:5vw'),
			_Rows(
				$elements
			)->class('grow')
		)->alignStart();
	}

    public function js()
    {
        return file_get_contents(resource_path('views/scripts/finance.js'));
    }


}
