<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Bank;
use App\View\Modal;

class BankUnitsForm extends Modal
{
	public $model = Bank::class;

    protected $unitsWithoutBank;
    protected $acceptableUnitsOptions;

	protected $_Title = 'finance.my-bank';
	protected $_Icon = 'library';

    public function created()
    {
        $this->unitsWithoutBank = auth()->user()->ownedUnitsWithoutBank();
        $this->acceptableUnitsOptions = $this->unitsWithoutBank->concat(
        	$this->model->id ? $this->model->units : []
        );
    }

	public function beforeSave()
	{
		$this->model->setUserId();
	}

	public function body()
	{
		return [
			_Input('general.name'),
			Bank::accountNumberInputs(),
			_Rows(
				_MultiSelect('related-units')
					->name('units')
					->options(
						$this->acceptableUnitsOptions->pluck('name', 'id')
					)->class('mb-0')
					->comment('finance.select-unit-for-contribution')
					->default(
						(!$this->model->id && ($this->unitsWithoutBank->count() == 1)) ?
							$this->unitsWithoutBank->pluck('id') :
							null
					),
			)->class('bg-info p-4 rounded-lg mb-4'),
			_Checkbox('finance.authorization-of-withdrawal')
				->name('ppa_authorized', false)
				->default($this->model->id ? 1 : 0),
			_SubmitButton('general.save')
				->redirect('banks-units.view')
		];
	}

	public function rules()
	{
		return array_merge([
			'ppa_authorized' => 'accepted',
		],
			Bank::validationRules()
		);
	}
}
