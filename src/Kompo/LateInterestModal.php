<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Invoice;
use App\View\Modal;
use Illuminate\Support\Carbon;

class LateInterestModal extends Modal
{
	protected $_Title = 'finance.add-late-interests';
	protected $_Icon = 'clock';

	public $class = 'overflow-y-auto mini-scroll';
    public $style = 'max-height:95vh';

    public $model = Invoice::class;

	public function handle()
	{
		$this->model->journalEntriesForInterest(
			request('amount'),
			request('transacted_at'),
			request('description'),
		);
	}

	public function body()
	{
		return [
			_Date('finance.charge-interests-at')->name('transacted_at', false)->default(date('Y-m-d')),

			_Input('finance.amount')->name('amount', false)->type('number')->step(0.01)
				->default($this->model->getLateInterest())
				->comment(__('finance.calculated-since').' '.$this->model->last_interest_date->translatedFormat('d M Y')),

			_Textarea('Description')->name('description', false),

			_SubmitButton('finance.charge-interests')
				->redirect('invoices.stage', [
	                'id' => $this->model->id,
	            ]),
		];
	}

	public function rules()
	{
		return [
			'transacted_at' => 'required|date',
			'amount' => 'required',
		];
	}
}
