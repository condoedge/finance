<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Finance\GlAccount;
use App\Models\Finance\Entry;
use Kompo\Form;

class TransactionEntryForm extends Form
{
	public $model = Entry::class;

	public function beforeSave()
	{
		$this->model->debit = request('debit') ?: 0;
		$this->model->credit = request('credit') ?: 0;
	}

	public function render()
	{
		return [
			$this->deleteEntry()
				->class('text-xl text-gray-300')
				->run('sumEntries'),

			_Input()->name('description')->placeholder('finance-description')
				->class('mb-0'),

			_Select()->name('gl_account_id')->placeholder('finance-account')
				->class('mb-0 max-w-xl')
				->options(
					GlAccount::getUnionOptions()
				),

			_Input()->name('debit')->placeholder('finance-debit')->type('number')->step(0.01)
				->class('w-28 mb-0')
				->run('sumEntries'),

			_Input()->name('credit')->placeholder('finance-credit')->type('number')->step(0.01)
				->class('w-28 mb-0')
				->run('sumEntries'),
		];
	}

	protected function deleteEntry()
	{
		return $this->model->id ? _Link() :

			_Link()->icon(_Sax('trash'))->emitDirect('deleted');
	}
}
