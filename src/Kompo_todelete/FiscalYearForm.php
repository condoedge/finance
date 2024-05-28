<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Crm\Union;
use App\View\Traits\IsDashboardCard;
use Kompo\Form;

class FiscalYearForm extends Form
{
    use IsDashboardCard;

	public $model = Union::class;

	public $id = 'fiscal-year-form-id';

	public function beforeSave()
	{
		if (!$this->model->isInitialFinanceInfoEditable()) {
			abort(403, __('error.cant-edit-eoy-closed'));
		}
	}

	public function render()
	{
		return [
			$this->cardHeader('finance.union-fiscal-year'),
			_Rows(
				$this->model->isInitialFinanceInfoEditable() ?

					_Date('start-date')->class('mb-0 noClear')
						->name('fiscal_year_start_date')
						->submit()
						->alert('finance.fiscal-year-star-changed') :

					_Html(carbon($this->model->fiscal_year_start_date)->translatedFormat('d M Y'))->class('font-bold text-lg')
			)->class('p-4 pt-0')
		];
	}

	public function rules()
	{
		return [
			'fiscal_year_start_date' => 'required',
		];
	}
}
