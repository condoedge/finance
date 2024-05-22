<?php

namespace Condoedge\Finance\Kompo;

use Kompo\Form;

class FinanceSettingsView extends Form
{
	public function render()
	{
		return [
			_Columns(
				_Rows(
					_PageTitle('finance.finance-settings')->class('mb-6'),
					new FiscalYearForm(currentUnion()->id),
					new AutoPaymentsPpaForm(currentUnion()->id),
					new UnionAnnualInterestForm(currentUnion()->id),
					new UnionNfsFeesForm(currentUnion()->id),
					new OtherFinanceSettingsForm(currentUnion()->id),
					auth()->user()->isSuperAdmin() ?
                        _Rows(
                            _CardHeader('finance.reset-financial-data'),
                            _Rows(
                                _Button('finance.restart-accounting')->class('bg-danger text-level1')
                                    ->selfGet('getResetFinancialDataForm')->inModal(),
                            )->class('p-4')
                        )->class('dashboard-card') : '',
                    ),
				_Rows(
					_PageTitle('finance.default-items')->class('mb-6'),
					new FundsTable(),
					new BanksTable(),
					new TaxAccountsTable(),
					new EndOfYearsTable(),
				)
			)
		];
	}

	public function getResetFinancialDataForm()
	{
		return new ResetFinancialDataForm();
	}
}
