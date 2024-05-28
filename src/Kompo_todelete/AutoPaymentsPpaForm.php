<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Crm\Union;
use App\View\Traits\IsDashboardCard;
use Kompo\Form;

class AutoPaymentsPpaForm extends Form
{
    use IsDashboardCard;

	public $model = Union::class;

	public function handle()
	{
		$this->model->auto_payments = request('auto_payments');
		$this->model->save();

		return $this->getAutoPaymentsForm(request('auto_payments'));
	}

	protected function getAutoPaymentsForm($autoPayments)
	{
		if ($autoPayments == 1) {
			$this->model->bnc_enabled = 1;
			$this->model->ppa = null;
			$this->model->save();

			return new BncUnionPpaForm($this->model->id);
		}
		if ($autoPayments == 2) {
			$this->model->bnc_enabled = null;
			$this->model->ppa = 1;
			$this->model->save();

			return new OtonomUnionPpaKeyForm($this->model->id);
		}
		if (is_null($autoPayments) || $autoPayments == 3) {
			$this->model->bnc_enabled = null;
			$this->model->ppa = null;
			$this->model->save();

			return;
		}
	}

	public function render()
	{
		return [
			$this->cardHeader('finance.automated-online-payments'),
			_Rows(
				_Select()->placeholder('finance.no-automated-payments-selected')
					->name('auto_payments')->options([
						3 => __('finance.we-will-not-use-automated-payments'),
						1 => __('finance.enable-automated-payments-with-NBC'),
						2 => __('finance.otonom-solution-online'),
					])
					->submit()->inPanel('auto-payments-panel'),
				_Panel(
					$this->getAutoPaymentsForm($this->model->auto_payments)
				)->id('auto-payments-panel'),
			)->class('p-4')
		];
	}

	public function rules()
	{
		return [
			//'auto_payments' => 'required',
		];
	}
}
