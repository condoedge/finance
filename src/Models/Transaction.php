<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Models\Acompte;
use Kompo\Auth\Models\Model;

class Transaction extends Model
{
    use \Kompo\Auth\Models\Traits\BelongsToUserTrait;
    use \Kompo\Auth\Models\Teams\BelongsToTeamTrait;

    use \Condoedge\Finance\Models\BelongsToInvoiceTrait;

    public const TYPE_MANUAL_ENTRY = 1;
    public const TYPE_CONTRIBUTION = 2;
    public const TYPE_INVOICE = 3;
    public const TYPE_INVOICE_PMT = 4;
    public const TYPE_BILL = 5;
    public const TYPE_BILL_PMT = 6;
    public const TYPE_INTEREST = 7;
    public const TYPE_EOY = 8;
    public const TYPE_NFS = 15;
    public const TYPE_BANKFEES = 20;
    public const TYPE_BANKINTEREST = 21;
    public const TYPE_INTERFUND = 30;

    public static $paymentTypes = [
        Transaction::TYPE_INVOICE_PMT,
        Transaction::TYPE_BILL_PMT,
    ];

    public static $resetChargeTypes = [
        Transaction::TYPE_CONTRIBUTION,
        Transaction::TYPE_INVOICE,
        Transaction::TYPE_BILL,
    ];

    public static $bankTransactionTypes = [
        Transaction::TYPE_BANKFEES,
        Transaction::TYPE_BANKINTEREST,
    ];

    /* RELATIONSHIPS */
    public function entries()
    {
    	return $this->hasMany(Entry::class);
    }

    public function mainEntry()
    {
        return $this->hasOne(Entry::class)->orderBy('id');
    }

    public function acompte()
    {
        return $this->hasOne(Acompte::class);
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    public function parentCharge()
    {
        return $this->invoice_id ? $this->invoice() : $this->bill();
    }

    public function parentCreditNote()
    {
        return $this->cn_invoice_id ? $this->belongsTo(Invoice::class, 'cn_invoice_id') : $this->belongsTo(Bill::class, 'cn_bill_id');
    }

    /* ATTRIBUTES */
    public function getCreditAttribute()
    {
        $this->loadMissing('entries');

        return $this->entries->sum('credit');
    }

    public function getDebitAttribute()
    {
        $this->loadMissing('entries');

        return $this->entries->sum('debit');
    }

    public function getDiffBalanceAttribute()
    {
        return $this->credit - $this->debit;
    }

    public function getTypeLabelAttribute()
    {
        return static::types()[$this->type] ?? null;
    }

    public function getColorAttribute()
    {
        return static::colors()[$this->type] ?? null;
    }

    public static function types()
    {
        return [
            static::TYPE_MANUAL_ENTRY => __('finance.journal-entry'),
            static::TYPE_CONTRIBUTION => __('finance.contribution-log'),
            static::TYPE_INVOICE => __('finance.invoice-log'),
            static::TYPE_INVOICE_PMT => __('finance.invoice-payment'),
            static::TYPE_BILL => __('finance.bill-log'),
            static::TYPE_BILL_PMT => __('finance.bill-payment'),
            static::TYPE_INTEREST => __('finance.late-interest'),
            static::TYPE_EOY => __('finance.end-of-year'),
            static::TYPE_NFS => __('finance.non-sufficient-funds'),
            static::TYPE_BANKFEES => __('finance.bank-account-fees'),
            static::TYPE_BANKINTEREST => __('finance.bank-account-interests'),
            static::TYPE_INTERFUND => __('finance.interfund-transfer'),
        ];
    }

    public static function colors()
    {
        return [
            static::TYPE_MANUAL_ENTRY => 'bg-warning',
            static::TYPE_CONTRIBUTION => 'bg-positive',
            static::TYPE_INVOICE => 'bg-positive bg-opacity-50',
            static::TYPE_INVOICE_PMT => 'bg-positive',
            static::TYPE_BILL => 'bg-level4 text-level1 bg-opacity-40',
            static::TYPE_BILL_PMT => 'bg-level4 text-level1',
            static::TYPE_INTEREST => 'bg-danger text-level1',
            static::TYPE_EOY => 'bg-gray-200',
            static::TYPE_NFS => 'bg-danger text-level1',
            static::TYPE_BANKFEES => 'bg-danger text-level1',
            static::TYPE_BANKINTEREST => 'bg-danger text-level1',
            static::TYPE_INTERFUND => 'bg-danger text-level1',
        ];
    }

    /* CALCULATED FIELDS */
    public function mainPaymentMethod()
    {
        return optional($this->mainEntry)->method_label;
    }

    public function isReadonly()
    {
        return $this->invoice_id || $this->bill_id;
    }

    public function isInvoicePayment()
    {
        return $this->type == static::TYPE_INVOICE_PMT;
    }

    public function isInvoiceInterest()
    {
        return $this->type == static::TYPE_INTEREST;
    }

    public function isBillPayment()
    {
        return $this->type == static::TYPE_BILL_PMT;
    }

    public function isVoidable()
    {
        if ($this->void || !auth()->user()->can('void', $this)) {
            return false;
        }

        if (($this->type == static::TYPE_INTEREST) && $this->parentCharge) {
            return !$this->parentCharge->payments()->count();
        }

        if (in_array($this->type, static::$resetChargeTypes) && $this->parentCharge) {
            return !$this->parentCharge->payments()->count() && !$this->parentCharge->interests()->count();
        }

        return true;
    }

    public function isPayment()
    {
        return in_array($this->type, static::$paymentTypes);
    }

    public function isBankTransaction()
    {
        return in_array($this->type, static::$bankTransactionTypes);
    }

    public function getPaymentNumber()
    {
        return $this->mainEntry?->payment_number;
    }

    /* SCOPES */
    public function scopeNotVoid($query)
    {
        $query->where(fn($q) => $q->where('void', '<>', 1)->orWhereNull('void'));
    }

    public function scopeIsPaymentType($query)
    {
        $query->whereIn('type', static::$paymentTypes);
    }

    public function scopeIsInterestType($query)
    {
        $query->where('type', static::TYPE_INTEREST);
    }


    /* ACTIONS */
    public function createEntry($accountId, $date, $credit = 0, $debit = 0, $paymentMethod = null, $description = null)
    {
        $entry = new Entry();
        $entry->gl_account_id = $accountId;
        $entry->transacted_at = $date;
        $entry->credit = $credit;
        $entry->debit = $debit;
        $entry->payment_method = $paymentMethod;
        if ($paymentMethod) {
            $entry->payment_number = request('payment_number');
        }
        $entry->description = $description ?: $this->description;
        $this->entries()->save($entry);

        return $entry;
    }

    public function createOptionalEntry($accountId, $date, $credit = 0, $debit = 0, $paymentMethod = null, $description = null)
    {
        if (!$debit && !$credit) {
            return;
        }

        $this->createEntry($accountId, $date, $credit, $debit, $paymentMethod, $description);
    }

    public function reverseEntriesWithRatio($tx, $ratio)
    {
        $this->entries->each(function($entry) use ($tx, $ratio) {

            $reversedEntry = $entry->replicate();

            $reversedEntry->transaction_id = $tx->id;
            $reversedEntry->transacted_at = $tx->transacted_at;

            $reversedEntry->debit = round($entry->credit * $ratio, 2);
            $reversedEntry->credit = round($entry->debit * $ratio, 2);

            $reversedEntry->save();

        });
    }

    public function reverseEntries($unvoidFlag = null)
    {
        $this->entries->each(function($entry) use ($unvoidFlag) {

            $reversedEntry = $entry->replicate();

            $reversedEntry->debit = $entry->credit;
            $reversedEntry->credit = $entry->debit;
            $reversedEntry->unvoid_flag = $unvoidFlag;

            $reversedEntry->save();

        });
    }

    public function markVoid()
    {
        if (!$this->union->acceptsFinanceChange($this->transacted_at)) {
            abort(403, balanceLockedMessage($this->union->latestBalanceDate()));
        }

        $oldAmount = $this->amount;

        $this->reverseEntries(1);

        $this->acompte()->first()?->delete();

        $this->void = 1;
        $this->amount = 0;
        $this->save();

        $this->handleParentAfterVoidChange($oldAmount);
    }

    protected function handleParentAfterVoidChange($amount)
    {
        if (!$this->parentCharge) {
            return;
        }

        if ($this->parentCreditNote) {
            $this->parentCreditNote->updateCreditNoteDueAmount(-$amount);
        }

        if (in_array($this->type, static::$resetChargeTypes)) {
            $this->parentCharge->markInitialStatus();

            $this->parentCharge->calc_due_amount = null; //Temporary until moved to calc_due_amount
            $this->parentCharge->save();

        } else {
            $this->parentCharge->markPayment();
        }
    }

    public function checkAndDelete()
    {
        //TODO: check fin d'annee

        $this->delete();
    }

    public function delete()
    {
        $this->entries->each->delete();

        parent::delete();
    }

    /* ELEMENTS */
    public function parentLink()
    {
        $parent = $this->parentCharge;

        if (!$parent) {
            return;
        }

        return _Link($parent->number_display)->rIcon('external-link')
            ->href($this->invoice_id ? 'invoices.stage' : 'bills.stage', [
                'id' => $this->invoice_id ?: $this->bill_id,
            ])
            ->class('text-2xl mt-1 font-bold text-level3 text-opacity-50');
    }

    public function voidPill()
    {
        if (!$this->void) {
            return;
        }

        return _Html('void')->icon('ban')->class('bg-danger bg-opacity-25 text-danger px-4 py-1 text-sm font-bold rounded-lg');
    }

    public function getVoidLink($withLabel = false)
    {
        if (!$this->isVoidable()) {
            return;
        }

        $link = _Link()->icon('ban')->class('text-level1')->outlined();

        $link = $withLabel ? $link->label('finance.void-transaction') : $link->balloon('finance.void-transaction', 'left');

        return $link->selfUpdate('getTransactionVoidModal', [
            'id' => $this->id,
        ])->inModal();
    }

    public function txTypePill()
    {
        //Get initials
        preg_match_all("/[A-Z]/", ucwords(strtolower($this->type_label)), $matches);

        return _Html(implode('', $matches[0]))
            ->balloon($this->type_label, 'right')
            ->class('text-xs px-2 py-1 rounded-lg inline-block')
            ->class($this->color);
    }
}
