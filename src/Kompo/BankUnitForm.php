<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Condo\Unit;
use Condoedge\Finance\Models\Bank;
use App\View\Modal;

class BankUnitForm extends Modal
{
	public $model = Bank::class;

    protected $unitsWithoutBank;
    protected $acceptableUnitsOptions;

	protected $_Title = 'finance.manage-unit-bank';
	protected $_Icon = 'library';

	protected $unitId;
	protected $unit;

    public function created()
    {
        $this->unitId = $this->parameter('unit_id');
        $this->unit = Unit::with('banks')->findOrFail($this->unitId);

        $this->model($this->unit->banks->first() ?: new Bank());
    }

	public function beforeSave()
	{
		$this->model->setUserId();
	}

    public function afterSave()
    {
    	$this->model->units()->sync([$this->unitId]);
    }

	public function headerButtons()
	{
		return _SubmitButton()->closeModal()->alert('finance.payment-information-saved');
	}

	public function body()
	{
		return [
			_Input('general.name')
				->default(__('finance.payment-account').' - '.__('Unit').' '.$this->unit->name),
			Bank::accountNumberInputs(),
		];
	}

	public function rules()
	{
		return Bank::validationRules();
	}
}
