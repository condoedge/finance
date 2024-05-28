<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Bank;
use App\View\Modal;

class BankForm extends Modal
{
	protected $_Title = 'finance.manage-bank';
	protected $_Icon = 'library';

	public $model = Bank::class;

	public function beforeSave()
	{
		$this->model->union_id = currentUnion()->id;
	}

	public function afterSave()
	{
		if(!request('account_id')) {

			if (!$this->model->account) {

		        $relatedAccount = new Account();

				$lastSibling = GlAccount::getLastSibling(GlAccount::CODE_CASH, $this->model->union_id);
				$relatedAccount->union_id = $this->model->union_id;
		        $relatedAccount->level = GlAccount::LEVEL_MEDIUM;
		        $relatedAccount->group = GlAccount::GROUP_ASSETS;
		        $relatedAccount->type = $lastSibling->getTranslations('type');
		        $relatedAccount->name = $this->model->display;
		        $relatedAccount->code = GlAccount::getNextCode($lastSibling);
			}else{
				$relatedAccount = $this->model->account;
			}

		}else{

			if ($this->model->account) {
				$this->model->account->bank_id = null;
				$this->model->account->save();
			}

			$relatedAccount = GlAccount::findOrFail(request('account_id'));
		}

		$relatedAccount->bank_id = $this->model->id;
		$relatedAccount->save();
	}

	public function completed()
	{
		//Fix default banks
		if (request('default_bank')) {
			currentUnion()->banks->each(function($bank){
				if ($bank->id !== $this->model->id) {
					$bank->default_bank = null;
					$bank->save();
				}
			});
		}

		$this->model->load('account');

		$this->option = [
			$this->model->account->id => $this->model->account->display
		];
	}

	public function headerButtons()
	{
		return _SubmitButton('general.save');
	}

	public function body()
	{
		return [
			_FlexBetween(
				_Input('general.name')->name('name')->class('flex-1'),
				_LinkGroup()->name('default_bank')->options([
					1 => _Sax('star-1', 28)->balloon('finance.default-bank', 'left')->class('p-2'),
				])->class('ml-4')->selectedClass('card-info text-white !mb-0', 'card-white text-gray-300 !mb-0'),
			)->alignEnd(),
			Bank::accountNumberInputs(),
			_Select('finance.related-account')
				->name('account_id', false)
				->options(
					GlAccount::inUnionGl()->cash()->get()
						->mapWithKeys(fn($account) => $account->getOption())
				)
				->value(optional($this->model->account)->id)
				->comment('finance.account-leave-blank'),
		];
	}

	public function rules()
	{
		return Bank::validationRules();
	}
}
