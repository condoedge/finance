<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Models\Entry;
use Condoedge\Finance\Models\Transaction;

class Bill extends Charge
{
    protected $casts = [
        'billed_at' => 'datetime',
        'due_at' => 'datetime',
        'worked_at' => 'datetime',
    ];

    public const STATUS_RECEIVED = 1;
    public const STATUS_APPROVAL_SENT = 2;
    public const STATUS_PAYMENT_APPROVED = 3;
    public const STATUS_PAYMENT_REFUSED = 4;
    public const STATUS_PARTIALLY_PAID = 5;
    public const STATUS_PAID = 6;
    public const STATUS_VOIDED = 10;

    public const PREFIX_BILL = 'BIL-';
    public const PREFIX_CREDITBILL = 'CDB-';
    public const NUMBER_COLUMN = 'bill_number';
    public const DATE_COLUMN = 'billed_at';

    protected static $mainTransactionTypes = [
        Transaction::TYPE_BILL,
    ];

    /* RELATIONSHIPS */
    public function stock()
    {
        return $this->morphOne(Stock::class, 'transactionSource');
    }

    /* SCOPES */
    public function scopeOpen($query)
    {
        $query->whereIn('status', [
            static::STATUS_PAYMENT_APPROVED,
            static::STATUS_PARTIALLY_PAID,
        ]);
    }

    /* ATTRIBUTES */
    public function getDisplayAttribute()
    {
    	$billNumber = $this->bill_number ? ($this->bill_number.' - ') : '';

    	return $billNumber.$this->supplier->display;
    }

    public function getNumberDisplayAttribute()
    {
        return $this->bill_number;
    }

    public static function statuses()
    {
        return [
            static::STATUS_RECEIVED => __('finance.Received'),
            static::STATUS_APPROVAL_SENT => __('finance.approval-sent'),
            static::STATUS_PAYMENT_APPROVED => __('Approved'),
            static::STATUS_PAYMENT_REFUSED => __('Refused'),
            static::STATUS_PARTIALLY_PAID => __('finance.Partial'),
            static::STATUS_PAID => __('finance.Paid'),
            static::STATUS_VOIDED => __('void'),
        ];
    }

    public static function colors()
    {
        return [
            static::STATUS_RECEIVED => 'bg-positive bg-opacity-30',
            static::STATUS_APPROVAL_SENT => 'bg-level4 bg-opacity-30',
            static::STATUS_PAYMENT_APPROVED => 'bg-info bg-opacity-30',
            static::STATUS_PAYMENT_REFUSED => 'bg-danger bg-opacity-30',
            static::STATUS_PARTIALLY_PAID => 'bg-warning bg-opacity-30',
            static::STATUS_PAID => 'bg-positive bg-opacity-30',
            static::STATUS_VOIDED => 'bg-danger bg-opacity-30',
        ];
    }

    /* CALCULATED FIELDS */
    public function isSentForApproval()
    {
        return $this->status == static::STATUS_APPROVAL_SENT;
    }

    public function isOverApprovalSent()
    {
        return in_array($this->status, [
            static::STATUS_APPROVAL_SENT,
            static::STATUS_PAYMENT_APPROVED,
            static::STATUS_PAYMENT_REFUSED,
            static::STATUS_PARTIALLY_PAID,
            static::STATUS_PAID,
        ]);
    }

    public function canPay()
    {
        return !$this->isReimbursment() && in_array($this->status, [
            static::STATUS_PAYMENT_APPROVED,
            static::STATUS_PARTIALLY_PAID,
        ]);
    }

    public function getEditRoute()
    {
        return $this->isReimbursment() ? 'bills-credit.form' : 'bill.form';
    }

    public static function getBillIncrement($unionId = null, $prefix = null)
    {
        return static::getIncrement($prefix ?: static::PREFIX_BILL, $unionId);
    }

    /* ACTIONS */
    public function markInitialStatus()
    {
        $this->status = static::STATUS_VOIDED;
        $this->approved_by = null;
        $this->approved_at = null;
        $this->save();
    }

    public function markSentForApproval()
    {
        $this->status = static::STATUS_APPROVAL_SENT;
        $this->save();
    }

    public function markAccepted()
    {
        if (!$this->mainTransaction) {
            $this->createJournalEntries();
            $this->createBillBacklogEntries();
        }

        $this->approved_by = auth()->id();
        $this->approved_at = now();
        $this->markPaymentStatus(static::STATUS_PAYMENT_APPROVED, static::STATUS_PARTIALLY_PAID, static::STATUS_PAID);
    }

    public function markRefused()
    {
        $this->approved_by = auth()->id();
        $this->approved_at = now();
        $this->markPaymentStatus(static::STATUS_PAYMENT_REFUSED, static::STATUS_PARTIALLY_PAID, static::STATUS_PAID);
    }

    public function markPayment()
    {
        $this->markPaymentStatus(static::STATUS_PAYMENT_APPROVED, static::STATUS_PARTIALLY_PAID, static::STATUS_PAID);
    }

    public function createNfsDetail($amount)
    {
        $invoiceDetail = new ChargeDetail();
        $invoiceDetail->name = __('finance.non-sufficient-funds-fee');
        $invoiceDetail->gl_account_id = $this->chargeDetails()->value('gl_account_id');  //take first account from other details
        $invoiceDetail->quantity = 1;
        $invoiceDetail->price = $amount;
        $this->chargeDetails()->save($invoiceDetail);
    }

    public function createJournalEntries($accountId = null)
    {
        $this->load('union');

        $tx = $this->createTransaction($this->total_amount, Transaction::TYPE_BILL, $this->display.' - '.__('finance.bill-due'));

        $tx->createEntry(
            $accountId ?: $this->union->glAccounts()->payables()->value('id'),
            $this->billed_at,
            $this->isReimbursment() ? 0 : $this->total_amount,
            $this->isReimbursment() ? $this->total_amount : 0,
        );

        $this->chargeDetails->each(
            fn($chargeDetail) => $tx->createEntry(
                $chargeDetail->gl_account_id,
                $this->billed_at,
                $this->getChargeDetailCredit($chargeDetail),
                $this->getChargeDetailDebit($chargeDetail),
                null,
                $chargeDetail->description,
            )
        );

        if (!$this->union->tax_accounts_enabled) {
            return;
        }

        $this->union->team->taxes->each(
            fn($tax) => $tx->createOptionalEntry(
                $this->union->glAccounts()->forTax($tax->id)->value('id'),
                $this->billed_at,
                $this->isReimbursment() ? $this->getAmountForTax($tax->id) : 0,
                $this->isReimbursment() ? 0 : $this->getAmountForTax($tax->id),
            )
        );
    }

    public function createBillBacklogEntries()
    {
        $this->chargeDetails()->get()
            ->each(fn($chargeDetail) => $chargeDetail->addToStockInventory($this->union, $this->billed_at, $this));
    }

    protected function getChargeDetailCredit($chargeDetail)
    {
        return $this->isReimbursment() ? $chargeDetail->total_amount_chd : 0;
    }

    protected function getChargeDetailDebit($chargeDetail)
    {
        return $this->isReimbursment() ? 0 : $chargeDetail->total_amount_chd;
    }

    public function createPayment($accountId, $date, $amount, $paymentMethod, $description)
    {
        if ($this->isReimbursment()) {
            abort(403, __('finance.payment-credit-note-not-allowed'));
        }

        $paymentTx = $this->createTransaction(
            $amount,
            Transaction::TYPE_BILL_PMT,
            $description ?: ($this->supplier->display.' - '.__('finance.bill-payment')),
            $date
        );

        $paymentTx->createEntry(
            $accountId,
            $date,
            $amount,
            0,
            $paymentMethod,
        );

        $paymentTx->createEntry(
            $this->mainTransaction->entries()->whereHas('account', fn($q) => $q->payables())->value('gl_account_id'),
            $date,
            0,
            $amount,
        );

        $this->markPayment();
    }

    public function useCreditNoteAsPayment($creditNote, $paidAt)
    {
        $maxAcceptedAmount = min(abs($creditNote->due_amount), abs($this->due_amount));
        $invRatio = $maxAcceptedAmount/abs($this->due_amount);
        $cnRatio = $maxAcceptedAmount/abs($creditNote->due_amount);

        $paymentTx = $this->createTransaction(
            $maxAcceptedAmount,
            Transaction::TYPE_BILL_PMT,
            __('finance.applying-payment-from-credit-note').' '.$creditNote->invoice_number,
            $paidAt
        );

        $paymentTx->createEntry(
            $this->mainTransaction->entries()->whereHas('account', fn($q) => $q->payables())->value('gl_account_id'),
            $paidAt,
            0,
            $maxAcceptedAmount,
            Entry::METHOD_ACOMPTE,
        );

        $paymentTx->createEntry(
            $this->mainTransaction->entries()->whereHas('account', fn($q) => $q->payables())->value('gl_account_id'),
            $paidAt,
            $maxAcceptedAmount,
            0,
        );

        $this->markPayment();

        $creditNote->updateCreditNoteDueAmount($maxAcceptedAmount);

        $paymentTx->cn_bill_id = $creditNote->id;
        $paymentTx->save();
    }

    protected function createTransaction($amount, $type, $description = null, $date = null)
    {
        $tx = new Transaction();
        $tx->amount = $amount;
        $tx->union_id = $this->union_id;
        $tx->transacted_at = $date ?: $this->billed_at;
        $tx->setUserId();
        $tx->type = $type;
        $tx->description = $description ?: $this->notes;
        $this->transactions()->save($tx);

        return $tx;
    }

    /* ELEMENTS */
    public function statusBadge()
    {
        return _Pill($this->status_label)
            ->class($this->color);
    }

    public function paidAtLabel()
    {
        return _Html('<b>'.__('finance.fully-paid-at').':</b> '.$this->lastPayment?->transacted_at);
    }
}
