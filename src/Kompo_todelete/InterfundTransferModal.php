<?php

namespace Condoedge\Finance\Kompo;

use App\Kompo\Common\Modal;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Transaction;

class InterfundTransferModal extends Modal
{
	protected $_Title = 'finance.interfund-transfer';

	public function handle()
	{
		$tx = new Transaction();
		$tx->setUnionId();
		$tx->setUserId();
		$tx->type = Transaction::TYPE_INTERFUND;
		$tx->description = __('finance.interfund-transfer');
		$tx->amount = request('transfer_amount');
		$tx->transacted_at = request('transfer_date');
		$tx->save();

		$tx->createEntry(
			request('from_account_id'),
			request('transfer_date'),
			request('transfer_amount'),
			0,
			null,
			$tx->description,
		);

		$accountA = GlAccount::getOrCreateInterfundAccount(GlAccount::CODE_INTERFUND_ASSET, request('from_fund_id'), request('to_fund_id'));

		$tx->createEntry(
			$accountA->id,
			request('transfer_date'),
			0,
			request('transfer_amount'),
			null,
			$tx->description,
		);

		$tx->createEntry(
			request('to_account_id'),
			request('transfer_date'),
			0,
			request('transfer_amount'),
			null,
			$tx->description,
		);

		$accountB = GlAccount::getOrCreateInterfundAccount(GlAccount::CODE_INTERFUND_LIABILITY, request('to_fund_id'), request('from_fund_id'));

		$tx->createEntry(
			$accountB->id,
			request('transfer_date'),
			request('transfer_amount'),
			0,
			null,
			$tx->description,
		);

		return new TransactionPreviewForm($tx->id);
	}

	public function body()
	{
		return [
			_Columns(
				_InputDollar('finance.transfer-this-amount')->name('transfer_amount'),
				_Date('finance.date-of-transfer')->name('transfer_date')->default(date('Y-m-d')),
			),
			_Rows(
				_Select('finance.from-this-account')->name('from_fund_id')->placeholder('finance.select-a-fund')
					->options(
						currentUnion()->funds()->pluck('name', 'id')
					),
				_Select()->name('from_account_id')->placeholder('finance.select-an-account')
					->optionsFromField('from_fund_id', 'getAssetAccountsFromFund'),
			)->class('card-gray-100 p-4'),
			_Rows(
				_Select('finance.to-this-account')->name('to_fund_id')->placeholder('finance.select-a-fund')
					->options(currentUnion()->funds()->pluck('name', 'id')),
				_Select()->name('to_account_id')->placeholder('finance.select-an-account')
					->optionsFromField('to_fund_id', 'getAssetAccountsFromFund'),
			)->class('card-gray-100 p-4'),
            _SubmitButton('finance.create-transfer')
            	->inSlidingPanel()->closeModal()->alert('Interfund transaction created!'),
		];
	}

	public function getAssetAccountsFromFund($fundId)
	{
		return GlAccount::inUnionGl()->forGroup(GlAccount::GROUP_ASSETS)->where('fund_id', $fundId)->get()
			->mapWithKeys(fn($account) => $account->getOption());
	}

	public function rules()
	{
		return [
            'transfer_amount' => 'required|numeric|gt:0',
            'transfer_date' => 'required',
            'from_fund_id' => 'required',
            'from_account_id' => 'required',
            'to_fund_id' => 'required',
            'to_account_id' => 'required',
        ];
	}
}
