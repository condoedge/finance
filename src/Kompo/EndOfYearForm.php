<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\AccountBalance;
use Condoedge\Finance\Models\Entry;
use Condoedge\Finance\Models\Transaction;
use App\View\Modal;

class EndOfYearForm extends Modal
{
    protected $fromDate;
    protected $deleteBalances;
    protected $isInitialBalances;

	public $class = 'overflow-y-auto mini-scroll';
	public $style = 'max-height: 95vh;max-width:100vw;width:768px';

  	protected $_Title = 'finance.end-of-year-balances';

    public function created()
    {
        $this->fromDate = $this->prop('from_date');
        $this->deleteBalances = $this->prop('delete_balances');

        $this->isInitialBalances = currentUnion()->balance_date == $this->fromDate;
        $this->isInitialBalancesEditable = currentUnion()->isInitialFinanceInfoEditable();
    }

	public function body()
	{
		return [
			_FlexBetween(
				_Html(__('finance.balances-lock-for').'&nbsp;&nbsp;'),
				_Html(carbon($this->fromDate)->translatedFormat('d M Y'))->class('text-xl font-bold')
			)->class('card-gray-200 p-4'),

			!$this->deleteBalances ? null :
				_Rows(
					_Html('finance.are-you-sure-delete-all-balances')
						->class('font-medium max-w-xl'),
					_FlexAround(
						_Button('finance.yes-delete')->class('bg-danger')
							->selfPost('deleteBalancesFrom', ['from_date' => $this->fromDate])
							->closeModal()
							->browse('eoys-table')
							->refresh('fiscal-year-form-id'),
						_Button('Cancel')->outlined()->closeModal(),
					),
					_Html('finance.after-deleting-restart-new-end-of-year')->icon('question-mark-circle')->class('text-sm max-w-xl')
				)->class('space-y-4 card-white p-4'),

			($this->isInitialBalances && $this->isInitialBalancesEditable) ?
				_Link('finance.review-initial-balances')->button()->href('initial-balances')->class('mb-4') : null,

			_Rows(
				AccountBalance::getBalancesTable($this->fromDate)
			)->class('card-white')
		];
	}

	public function deleteBalancesFrom($fromDate)
	{
		//Delete Balances
		$this->accountBalancesForDateQuery($fromDate)->delete();

		$eoyDate = carbon($fromDate)->addDays(-1)->format('Y-m-d');

		//Delete Eoy BNR Txs and their entries
		Transaction::where('union_id', currentUnion()->id)
			->where('transacted_at', $eoyDate)
			->where('type', Transaction::TYPE_EOY)->get()->each->delete();
	}

	protected function accountBalancesForDateQuery($fromDate = null)
	{
		$query = AccountBalance::with('account')->whereIn('account_id', GlAccount::inUnionGl()->pluck('id'));

		if ($fromDate) {
			$query = $query->where('from_date', $fromDate);
		}

		return $query;
	}
}
