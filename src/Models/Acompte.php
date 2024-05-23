<?php

namespace Condoedge\Finance\Models;

use App\Models\Condo\Unit;
use Condoedge\Finance\Models\Entry;
use Condoedge\Finance\Models\Transaction;
use Kompo\Auth\Models\Model;
use App\Models\Traits\BelongsToUnit;
use Illuminate\Database\Eloquent\SoftDeletes;

class Acompte extends Model
{
    use SoftDeletes,
        BelongsToUnit;

    /* RELATIONSHIPS */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    /* ATTRIBUTES */

    /* CALCULATED FIELDS */

    /* SCOPES */

    /* ACTIONS */
    public static function createForUnit($unit, $accountId, $date, $amount, $paymentMethod, $description)
    {
        $paymentTx = static::createTransaction($date, $unit, $amount, $description ?: (__('Advance payment for unit').' '.$unit->display));

        $paymentTx->createEntry(
            $accountId,
            $date,
            0,
            $amount,
            $paymentMethod,
        );

        $paymentTx->createEntry(
            $unit->union->accounts()->acompte()->value('id'),
            $date,
            $amount,
            0,
        );

        static::addOrDelete($unit->id, +$amount, $paymentTx->id);
    }

    protected static function createTransaction($date, $unit, $amount, $description, $invoiceId = null)
    {
        $tx = new Transaction();
        $tx->amount = $amount;
        $tx->union_id = $unit->union_id;
        $tx->transacted_at = $date;
        $tx->setUserId();
        $tx->type = Transaction::TYPE_INVOICE_PMT;
        $tx->description = $description;

        $tx->invoice_id = $invoiceId;
        $tx->save();

        return $tx;
    }

    public static function addOrDelete($customerId, $amount, $transactionId = null)
    {
        //For now just adding... delete to do when optimization becomes relevant...
        $acompte = new static();
        $acompte->customer_id = $customerId;
        $acompte->amount = $amount;
        $acompte->transaction_id = $transactionId;
        $acompte->save();
    }

    /* ELEMENTS */
}
