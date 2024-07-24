<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Finance\ChargeDetail;
use Kompo\Table;

class ChargeDetailsTable extends Table
{
    protected $invoiceId;
    protected $billId;

    public function created()
    {
        $this->billId = $this->prop('bill_id');
        $this->invoiceId = $this->prop('invoice_id');
    }

    public function query()
    {
        if ($this->billId) {
            return ChargeDetail::where('bill_id', $this->billId);
        }
        if ($this->invoiceId) {
            return ChargeDetail::where('invoice_id', $this->invoiceId);
        }
    }

    public function headers()
    {
        return [
            _Th('finance-product-service'),
            _Th('finance-qty')->class('text-right'),
            _Th('finance-price')->class('text-right'),
            _Th('finance-taxes')->class('text-right'),
            _Th('finance-amount')->class('text-right'),
        ];
    }

    public function render($chargeDetail)
    {
    	return _TableRow(
            _Html($chargeDetail->name_chd),
            _Html($chargeDetail->quantity_chd)->class('text-right'),
            _Currency($chargeDetail->price_chd)->class('text-right'),
            _Currency($chargeDetail->tax_amount_chd)->class('text-right'),
            _Currency($chargeDetail->total_amount_chd)->class('text-right font-semibold'),
        );
    }
}
