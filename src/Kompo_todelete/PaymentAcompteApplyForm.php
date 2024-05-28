<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Invoice;
use App\View\Form;

class PaymentAcompteApplyForm extends Form
{
	public $model = Invoice::class;

	protected $submitInfoPanelId = 'submit-info-panel';

	protected function getAcompteValue()
	{
		return $this->model->customer->acompteValue();
	}

	public function handle()
	{
        if (!$this->model->union->acceptsFinanceChange(request('transacted_at'))) {
            abort(403, balanceLockedMessage($this->model->union->latestBalanceDate()));
        }

		if (!($acompte = request('acompte'))) {
			abort(403, __('Cannot enter a payment with zero amount.'));
		}

		if ($this->getAcompteValue() < $acompte) {
			abort(403, __('finance.unit-doesnt-have-enough-balance'));
		}

		$this->model->useAcompteAsPayment($acompte, request('transacted_at'), request('write_off'));

		return redirect()->route('invoices.stage', [
            'id' => $this->model->id,
        ]);
	}

	public function render()
	{
		$unitMaxAcompte = $this->getAcompteValue();
		$bestGuessAmount = min($unitMaxAcompte, $this->model->due_amount);

		return _Rows(
			_Html('There are '.$unitMaxAcompte.' in advance payments for this unit. Do you want to use them?')
				->class('mb-4'),
			_Date('finance.payment-date')->name('transacted_at')->default(date('Y-m-d')),
			//_Range('Select amount')->name('acompte')->min(0)->max($unitMaxAcompte)->step(0.01)->default($unitMaxAcompte),
			_Input('Select amount')->name('acompte')->default($bestGuessAmount)
				->type('number')->step(0.01)
				->selfGet('getSubmitInfoPanel')->inPanel($this->submitInfoPanelId),

			_Panel(
				$this->getSubmitInfoPanel($bestGuessAmount)
			)->id($this->submitInfoPanelId)
		);
	}

	public function getSubmitInfoPanel($amount)
	{
		return PaymentEntryForm::getWriteOffElements('Use advanced payments', $this->model->due_amount, $amount);
	}

	public function rules()
	{
		return [
			'transacted_at' => 'required|date',
			'acompte' => 'required|numeric|regex:/^\d+(\.\d{1,2})?$/',
		];
	}
}
