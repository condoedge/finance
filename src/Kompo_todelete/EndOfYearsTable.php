<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\AccountBalance;
use Condoedge\Finance\Models\Transaction;
use Condoedge\Finance\Kompo\EndOfYearForm;
use App\View\Traits\IsDashboardCard;
use Kompo\Table;

class EndOfYearsTable extends Table
{
    use IsDashboardCard;

    public $layout = 'Table';

    public $id = 'eoys-table';

    protected $unionAccountIds;
    protected $initialBalanceDate;
    protected $latestBalanceDate;

    public function created()
    {
        $this->unionAccountIds = currentUnion()->allAccounts()->pluck('id');
        $this->initialBalanceDate = currentUnion()->balance_date;

        $this->latestBalanceDate = AccountBalance::whereIn('account_id', $this->unionAccountIds)->orderByDesc('from_date')->value('from_date');
    }

    public function query()
    {
        $existingBalances = AccountBalance::whereIn('account_id', $this->unionAccountIds)
            ->groupBy('from_date')
            ->pluck('from_date');

        $existingBalances = $existingBalances->count() ? $existingBalances : collect(['from_date' => $this->initialBalanceDate]);

        return $existingBalances->concat($this->eoyDateOptions())->unique()->sortDesc();
    }

    public function top()
    {
        return $this->cardHeader('finance.balance-lock-eoy');
    }

    public function headers()
    {
        return [
            _Th('finance.fiscal-year'),
            _Th('finance.status'),
            _Th('finance.action'),
            _Th()
        ];
    }

    public function render($balanceDate)
    {
        if ($balanceDate < $this->initialBalanceDate) {
            return; //Irrelevant
        }

        $isInitialBalance = $balanceDate == $this->initialBalanceDate;
        $closedStatus = $isInitialBalance || AccountBalance::whereIn('account_id', $this->unionAccountIds)
                                                ->where('from_date', $balanceDate)->count();

        $fiscalYearLabel = carbon($balanceDate)->translatedFormat('d M Y');

        $canRunEoy = carbon($balanceDate)->diffInDays(carbon($this->latestBalanceDate)) <= 366;

    	return _TableRow(
            _Html($fiscalYearLabel),
            _Html($closedStatus ? __('general.closed') : __('general.open')),
            _Flex(
                $isInitialBalance ? _Pill('finance.initial-balances')->class('bg-gray-200 text-gray-700') : (
                    $closedStatus ?
                        _Pill('finance.preview')->class('bg-info text-white') :
                        (!$canRunEoy ? null : _Button('finance.run-end-of-year')->small()->class('!px-4')
                            ->selfPost('runEndOfYearBalances', [
                                'date' => $balanceDate,
                            ])->inModal()
                            ->browse()
                            ->refresh('fiscal-year-form-id')
                            ->alert('finance.eoy-balances-closed-success'))
                )
            )->class('text-xs'),
            ($balanceDate != $this->latestBalanceDate) ? _Html() :
                _Link()->icon(_Sax('trash',20))->class('text-gray-600')->balloon('finance.delete-balances-for-this-date', 'left')
                    ->selfUpdate('deleteAccountBalancesFor', [
                        'from_date' => $balanceDate,
                    ])->inDrawer()
        )->selfUpdate('getEoyModal', ['date' => $balanceDate])->inModal();
    }

    public function getEoyModal($date)
    {
        return new EndOfYearForm([
            'from_date' => $date
        ]);
    }

    public function deleteAccountBalancesFor($date)
    {
        return new EndOfYearForm([
            'from_date' => $date,
            'delete_balances' => true,
        ]);
    }

    public function runEndOfYearBalances($date)
    {
        GlAccount::inUnionGl()->bnr()->get()->each(
            fn($bnrAccount) => $this->createBnrEntriesFor($bnrAccount, $date)
        );

        return $this->getEoyModal($date);
    }

    protected function createBnrEntriesFor($bnrAccount, $boyDate)
    {
        $totalAmount = 0;

        $eoyDate = carbon($boyDate)->addDays(-1)->format('Y-m-d');

        $tx = new Transaction();
        $tx->setUnionId();
        $tx->setUserId();
        $tx->transacted_at = $eoyDate;
        $tx->type = Transaction::TYPE_EOY;
        $tx->description = __('finance.eoy-surplus-lock').' '.$bnrAccount->display;
        $tx->amount = 0; //Temporary
        $tx->save();

        $ulbd = currentUnion()->latestBalanceDate(); //I had to injected otherwise it gets recalculated after inserting balances...

        foreach ($this->getAccountIds($bnrAccount) as $key => $account) {

            [$creditAmount, $debitAmount] = GlAccount::getAccountsCreditDebit([$account->id], $eoyDate, $ulbd);

            $debit = GlAccount::calcDebit($account->group, $creditAmount, $debitAmount);
            $credit = GlAccount::calcCredit($account->group, $creditAmount, $debitAmount);

            if (GlAccount::isEoyZeroed($account->group)) {
                if ((abs($debit) > 0) || (abs($credit)>0)) {

                    //Inverting credit and debit
                    $tx->createEntry($account->id, $eoyDate, $debit, $credit, null, __('finance.eoy-net-balance').' '.$account->display);

                    $totalAmount += $debit - $credit;
                }

                $account->addZeroBalance($boyDate);

            } else {
                if ($debit > 0) {
                    $account->addDebitBalance($boyDate, $debit);
                }
                if ($credit < 0) {
                    $account->addCreditBalance($boyDate, $credit);
                }
            }


        }

        $bnrDebit = ($totalAmount > 0) ? 0 : -$totalAmount;
        $bnrCredit = ($totalAmount < 0) ? 0 : $totalAmount;

        $tx->createEntry($bnrAccount->id, $eoyDate, $bnrDebit, $bnrCredit, null, __('finance.eoy-net-balance').' '.$bnrAccount->display);

        $tx->amount = $tx->entries()->sum('debit');
        $tx->save();

        if ($bnrDebit) {
            $bnrAccount->addDebitBalance($boyDate, $bnrDebit);
        } else {
            $bnrAccount->addCreditBalance($boyDate, $bnrCredit);
        }
    }

    protected function getAccountIds($bnrAccount)
    {
        $accountIds = GlAccount::inUnionGl();

        if ($bnrAccount->code == GlAccount::CODE_BNR_OPERATING) {
            $accountIds = $accountIds->where(fn($q) => $q->where('fund_id', $bnrAccount->fund_id)->orWhereNull('fund_id'));
        }else{
            $accountIds = $accountIds->where('fund_id', $bnrAccount->fund_id);
        }

        return $accountIds->get();
    }

    protected function eoyDateOptions()
    {
        $dt0 = currentUnion()->currentFiscalYearStart();

        return [
            $dt0->copy()->addYear(1)->format('Y-m-d'),
            $dt0->copy()->format('Y-m-d'),
            $dt0->copy()->addYear(-1)->format('Y-m-d'),
        ];
    }
}
