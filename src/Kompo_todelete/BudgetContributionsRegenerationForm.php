<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Budget;
use Kompo\Form;

class BudgetContributionsRegenerationForm extends Form
{
	public $model = Budget::class;

	public function handle()
	{
		$this->model->regenerateContributionsAfter(request('regeneration_date'));

		return redirect()->route('budget-preview.view', ['budget_id' => $this->model->id]);
	}

	public function render()
	{
		return [
			_Columns(
				_WarningMessage(
					_Html('finance.added-new-funds'),
					_Html('<br><b>'.__('finance.newly-added-funds').': </b>'.$this->model->addedFundsAdhoc()->implode('name', ' | '))
				),
				_Rows(
					_Html(__('finance.to-fix-this').
						' <a class="underline" href="'.route('budget-estimate.view', ['budget_id' => $this->model->id]).'">'.
						'<b>'.__('finance.new-estimated-amounts').'</b>'.
						'</a>. '.
						__('finance.then-if-everything-is-ok')
					),
					_Columns(
						_Date('finance.delete-and-recreate-contributions')->name('regeneration_date')->default(date('Y-m-d'))->class('mb-0'),
						_Button('finance.regenerate-future-invoices')->submit(),
					)->alignEnd(),
				)->class('dashboard-card p-4 space-y-2'),
			)->class('mb-4'),
		];
	}

	public function rules()
	{
		return [
			'regeneration_date' => 'required',
		];
	}
}
