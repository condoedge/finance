<?php

namespace Condoedge\Finance\Kompo;

class TransactionsMiniTable extends TransactionsTable
{
    protected $invoiceId;
    protected $billId;

    protected $alwaysShowVoid = true;

    public function created()
    {
        $this->invoiceId = $this->prop('invoice_id');
        $this->billId = $this->prop('bill_id');
    }

    public function query()
    {
        $query = parent::query();

        if ($this->invoiceId) {
            return $query->where('invoice_id', $this->invoiceId);
        }

        if ($this->billId) {
            return $query->where('bill_id', $this->billId);
        }

        abort(403, 'finance.no-parent-invoice-or-bill-given');
    }

    public function top()
    {
        return;
    }

    protected function voidLinkWithAction($transaction)
    {
        return $transaction->getVoidLink()?->class('text-lg');  //overridden
    }

    public function getTransactionVoidModal($id)
    {
        return new TransactionVoidModal($id, [
            'refresh_id' => 'charge-stage-page',
        ]);
    }
}
