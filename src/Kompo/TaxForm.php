<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Tax;
use App\View\Traits\IsDashboardModal;
use Kompo\Form;

class TaxForm extends Form
{
	use IsDashboardModal;

	public $model = Tax::class;

	protected $newModel = false;

	public function created()
	{
		$this->newModel = !$this->model->id;
	}

	public function beforeSave()
	{
		if($this->model->rate > 1)
			$this->model->rate = request('rate')/100;

		$this->model->setTeamId();
	}

	public function afterSave()
	{
		if ($this->newModel) {
			$this->model->createTaxAccount(currentUnion()->id);
		}
	}

	public function render()
	{
		return [
			$this->modalHeader('finance.manage-tax', 'money'),
			$this->modalBody(
				_Translatable('Name')->name('name'),
				_Input(__('Rate').' '.__('in%'))
					->name('rate'),
				_SubmitButton('general.save')
			)
		];
	}

	public function rules()
	{
		return [
			'name' => 'required',
			'rate' => 'required|numeric'
		];
	}
}
