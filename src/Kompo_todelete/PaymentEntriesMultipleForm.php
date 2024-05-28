<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Entry;
use App\View\Modal;

class PaymentEntriesMultipleForm extends Modal
{
	protected $_Title = 'finance.record-payment';
	protected $_Icon = 'cash';
	
	public $class = 'overflow-y-auto mini-scroll';
    public $style = 'max-height:95vh';

	protected $modelsIds;
	protected $models;

	protected $modelType;

	public function created()
	{
		$this->modelType = $this->parameter('type');

		if (!in_array($this->modelType, ['invoice', 'bill'])) {
			abort(403);
		}

		$this->store(['modelsIds' => $this->store('modelsIds') ?: explode(',', request('itemIds'))]);
		$this->modelsIds = $this->store('modelsIds');

		$model = 'App\\Models\\Finance\\'.ucfirst($this->modelType);

		$this->models = $model::whereIn('id', $this->modelsIds)->get();
	}

	public function handle()
	{
		$this->models->each(function($model){
			$model->union->checkIfDateAcceptable(request('transacted_at'));
		});

		$this->models->each(function($model){

			if (!$model->due_amount) {
				return;
			}

			$model->createPayment(
	            request('account_id'),
	            request('transacted_at'),
	            $model->due_amount,
	            request('payment_method'),
            	request('description'),
			);
		});
	}

	public function body()
	{
		return [
			_FlexBetween(
				_Html(__('finance.recording-total-payment').':'),
				_Currency($this->models->map->due_amount->sum())->class('font-bold text-lg'),
			)->class('p-4 card-gray-100 space-x-4'),

			_DateLockErrorField(),

			_Date('finance.payment-date')->name('transacted_at')->default(date('Y-m-d')),

			GlAccount::cashAccountsSelect(),

			Entry::paymentMethodsSelect(),

			_Textarea('Description')->name('description'),

			_SubmitButton('finance.record-payment')
				->redirect(
					($this->modelType == 'invoice') ? 'invoices.table' : 'bills.table'
				),
		];
	}

	public function rules()
	{
		return [
			'transacted_at' => 'required|date',
			'account_id' => 'required',
			'payment_method' => 'required',
		];
	}
}
