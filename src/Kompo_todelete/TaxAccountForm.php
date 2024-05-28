<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Tax;
use App\View\Traits\IsDashboardModal;
use Kompo\Form;

class TaxAccountForm extends Form
{
	use IsDashboardModal;

	public $model = GlAccount::class;

	public function beforeSave()
	{
    	$this->model->setUnionId();
	}

	public function render()
	{
		return [
			$this->modalHeader('finance.manage-account', 'card-edit'),
			$this->modalBody(
				/*_Select('Related tax')->name('tax_id')
					->options(Tax::getTaxesOptions()),*/
				_Input('finance.account-number')->name('number')
					->comment('finance.this-is-the-tax-account-text'),
				_SubmitButton('general.save')
			)
		];
	}

	public function rules()
	{
		return [
			'tax_id' => 'required',
		];
	}
}
