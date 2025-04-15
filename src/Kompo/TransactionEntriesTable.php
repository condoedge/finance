<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Finance\Transaction;
use Kompo\Table;

class TransactionEntriesTable extends Table
{
    protected $transactionId;

    public $class = 'pb-8';

    public $perPage = 100;

    public function created()
    {
        $this->transactionId = $this->prop('transaction_id');
    }

    public function query()
    {
        return Transaction::findOrFail($this->transactionId)->entries();
    }

    public function headers()
    {
        return [
            _Th('#'),
            _Th('Description'),
            _Th('Account'),
            _Th('Debit'),
            _Th('Credit'),
        ];
    }

    public function render($entry)
    {
    	return _TableRow(
            _Html($entry->id)->class('text-gray-300 text-xs'),
            _Html($entry->description),
            _Html($entry->glAccount?->display),
            _FinanceCurrency($entry->debit),
            _FinanceCurrency($entry->credit)
        );
    }
}
