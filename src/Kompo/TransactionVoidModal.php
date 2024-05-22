<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Entry;
use Condoedge\Finance\Models\Transaction;
use App\View\Modal;

class TransactionVoidModal extends Modal
{
	protected $_Title = 'finance.void-transaction-question';
	protected $_Icon = 'ban';

	public $class = 'overflow-y-auto mini-scroll';
    public $style = 'max-height:95vh';

    public $model = Transaction::class;

    protected $refreshId;

    public function created()
    {
    	$this->refreshId = $this->prop('refresh_id');
    }

	public function handle()
	{
        if (!auth()->user()->can('void', $this->model)) {
            return abort(403, __('finance.you-are-not-allow-to-modify-this-transaction'));
        }

		$newTransaction = $this->model->replicate(); //receives invoice or bill id

		$this->model->markVoid();

		if (!request('nfs_toggle')) {
			return;
		}

		$parentCharge = $newTransaction->parentCharge;

		if (!$parentCharge) {
            return;
        }

        $amount = request('nfs_fees');

        $parentCharge->createNfsDetail($amount);

		//Set new transaction info
		$newTransaction->setUserId();
		$newTransaction->type = Transaction::TYPE_NFS;
		$newTransaction->description = __('finance.non-sufficient-funds-fee');
		$newTransaction->amount = $amount;
		$newTransaction->transacted_at = request('nfs_fee_date');
		$newTransaction->save();

		$newTransaction->createEntry(
			GlAccount::inUnionGl()->nfs()->value('id'),
			$newTransaction->transacted_at,
			$newTransaction->amount,
			0,
			Entry::METHOD_JOURNAL_ENTRY,
			$newTransaction->description,
		);

		$newTransaction->createEntry(
			GlAccount::inUnionGl()->receivables()->value('id'),
			$newTransaction->transacted_at,
			0,
			$newTransaction->amount,
			Entry::METHOD_JOURNAL_ENTRY,
			$newTransaction->description,
		);
	}

	public function body()
	{
		$submitBtn = _SubmitButton('yes')->icon('icon-check');

		return [
			!$this->model->isPayment() ? null : _Rows(
				_Toggle('finance.add-nfs-fees-optional')->name('nfs_toggle', false)->class('w-64 mb-0')->toggleId('nfs_input'),
				_Rows(
					_DollarInput('finance.nfs-fee-amount')->name('nfs_fees', false)->default(currentUnion()->nfs_fees),
					_Date('finance.fee-date')->name('nfs_fee_date', false)->default(date('Y-m-d')),
				)->id('nfs_input'),
			)->class('card-gray-100 space-y-4 p-4 items-center'),
			_FlexAround(
				$this->refreshId ? $submitBtn->refresh($this->refreshId) : $submitBtn->browse(),
				_Button('Cancel')->class('bg-level3')->closeModal(),
			),

			_ErrorAlert('finance.once-void-you-cant-unvoid-transaction')->class('text-xs mt-4 max-w-xl'),
		];
	}
}
