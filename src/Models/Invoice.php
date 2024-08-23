<?php

namespace Condoedge\Finance\Models;

use App\Mail\ContributionNotification;
use App\Models\Condo\Unit;
use App\Models\Crm\Customer;
use Condoedge\Finance\Models\Acompte;
use Condoedge\Finance\Models\Entry;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class Invoice extends Charge
{
    use \Kompo\Auth\Models\Teams\BelongsToTeamTrait;
    use \Condoedge\Crm\Models\BelongsToPersonTrait;

    protected $toExtendCasts = [
        'invoiced_at' => 'datetime',
        'due_at' => 'datetime',
        'status' => InvoiceStatusEnum::class,
    ];

    public const STATUS_DRAFT = 1;
    public const STATUS_APPROVED = 2;
    public const STATUS_SENT = 3;
    public const STATUS_PARTIALLY_PAID = 4;
    public const STATUS_PAID = 5;
    public const STATUS_VOIDED = 10;

    public const PREFIX_INVOICE = 'INV-';
    public const PREFIX_CREDITNOTE = 'CDN-';
    public const NUMBER_COLUMN = 'invoice_number';
    public const DATE_COLUMN = 'invoiced_at';

    protected static $mainTransactionTypes = [
        Transaction::TYPE_CONTRIBUTION,
        Transaction::TYPE_INVOICE,
        Transaction::TYPE_INTEREST,
    ];

    /* RELATIONSHIPS */
    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }

    public function stock()
    {
        return $this->morphOne(Stock::class, 'transactionSource');
    }

    /* SCOPES */
    public function scopeDraft($query)
    {
        $query->where('status', InvoiceStatusEnum::DRAFT);
    }

    public function scopeNotDraft($query)
    {
        $query->where('status', '<>', InvoiceStatusEnum::DRAFT);
    }

    public function scopeApproved($query)
    {
        $query->where('status', InvoiceStatusEnum::APPROVED);
    }

    public function scopeOpen($query)
    {
        $query->whereIn('status', [
            InvoiceStatusEnum::SENT,
            InvoiceStatusEnum::APPROVED,
            InvoiceStatusEnum::PARTIALLY_PAID,
        ]);
    }

    public function scopeForBalance($query)
    {
        $query->open()->with('payments', static::ITEMS_RELATION.'.taxes');;
    }

    public function scopeIsContribution($query)
    {
        $query->whereNotNull('budget_id');
    }

    public function scopeForUnit($query, $unitId)
    {
        $query->where('customer_type', 'unit')->where('customer_id', $unitId);
    }

    public function scopeForDate($query, $date)
    {
        $query->where('invoiced_at', carbon($date)->format('Y-m-d'));
    }

    /* CALCULATED FIELDS */
	public static function getInvoiceIncrement($unionId = null, $prefix = null)
	{
        return static::getIncrement($prefix ?: static::PREFIX_INVOICE, $unionId);
	}

    public function isDraft()
    {
        return $this->status == InvoiceStatusEnum::DRAFT;
    }

    public function isLate()
    {
        return !$this->canApprove() && $this->due_amount && (
            $this->due_at->diffInDays(Carbon::now(), false) > $this->team->getLateDays()
        );
    }

    public function getLateInterest()
    {
        if (!$this->isLate()) {
            return 0;
        }

        $nbOfLateDays = $this->last_interest_date->diffInDays(Carbon::now(), false);

        return round(($nbOfLateDays / 365 * $this->union->late_interest / 100) * $this->due_amount, 2);
    }

    public function isContribution()
    {
        return $this->budget_id;
    }

    public static function whereSendable($unionId)
    {
        return static::where('union_id', $unionId)->where(fn($q) => $q->draft()->orWhere(fn($q) => $q->approved()));
    }

    public static function chargeableContributions($chargeDate, $unionId)
    {
        return static::whereSendable($unionId)->isContribution()->forDate($chargeDate);
    }

    public static function getDueInvoices($unitId)
    {
        return static::with('payments')->forUnit($unitId)
            ->where('invoiced_at', '<=', Carbon::now()->addDays(2))->get()->filter(
                fn($invoice) => $invoice->due_amount >= 0.01
            );
    }

    public function getEditRoute()
    {
        return $this->isReimbursment() ? 'finance.invoice-credit-form' : 'finance.invoice-form';
    }

    public function canPay()
    {
        return !$this->isReimbursment() && in_array($this->status, [
            InvoiceStatusEnum::APPROVED,
            InvoiceStatusEnum::SENT,
            InvoiceStatusEnum::PARTIALLY_PAID,
        ]);
    }

    public function canApprove()
    {
        return in_array($this->status, [
            InvoiceStatusEnum::DRAFT,
            InvoiceStatusEnum::VOIDED,
        ]);
    }

    public function canMarkSent()
    {
        return in_array($this->status, [
            InvoiceStatusEnum::DRAFT,
            InvoiceStatusEnum::APPROVED,
        ]);
    }

    /* ATTRIBUTES */
    public function getNumberDisplayAttribute()
    {
        return $this->invoice_number;
    }

    public function getLastInterestDateAttribute()
    {
        return carbon(
            $this->transactions()->where('type', Transaction::TYPE_INTEREST)->notVoid()
                ->orderByDesc('transacted_at')->value('transacted_at') ?:
                $this->due_at->addDays($this->union->getLateDays())
        );
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }

    public function getColorAttribute(): string
    {
        return $this->status->classes();
    }

    public static function statuses()
    {
        return [
            InvoiceStatusEnum::DRAFT->value => __('finance.draft'),
            InvoiceStatusEnum::APPROVED->value => __('finance.approved'),
            InvoiceStatusEnum::SENT->value => __('finance.sent'),
            InvoiceStatusEnum::PARTIALLY_PAID->value => __('finance.partial'),
            InvoiceStatusEnum::PAID->value => __('finance.paid'),
            InvoiceStatusEnum::VOIDED->value => __('finance.void'),
        ];
    }

    public static function colors()
    {
        return [
            InvoiceStatusEnum::DRAFT->value => 'bg-graylight text-graydark',
            InvoiceStatusEnum::APPROVED->value => 'bg-infolight text-info',
            InvoiceStatusEnum::SENT->value => 'bg-graylight bg-graydark',
            InvoiceStatusEnum::PARTIALLY_PAID->value => 'bg-warninglight text-warningdark',
            InvoiceStatusEnum::PAID->value => 'bg-greenlight text-greendark',
            InvoiceStatusEnum::VOIDED->value => 'bg-dangerlight text-dangerdark',
        ];
    }

    /* ACTIONS */
    public function markApprovedWithJournalEntries()
    {
        if ($this->transactions()->notVoid()->count() || !$this->canApprove()) {
            return;
        }

        if (!$this->team->acceptsFinanceChange($this->invoiced_at)) {
            abort(403, balanceLockedMessage($this->union->latestBalanceDate()));
        }

        $this->journalEntriesAsInvoice();

        //$this->createInvoiceBacklogEntries();

        $this->markApproved();
    }

    public function journalEntriesAsInvoice()
    {
        $tx = $this->createTransaction($this->total_amount);

        $this->createMainEntry($tx);

        $this->createInvoiceDetailsEntries($tx, true);
    }

    public function createInvoiceBacklogEntries()
    {
        $this->chargeDetails()->get()
            ->each(fn($chargeDetail) => $chargeDetail->reduceFromStockInventory($this->union, $this->invoiced_at, $this));
    }

    public function journalEntriesForInterest($amount, $date, $description = null)
    {
        $tx = $this->createTransaction($amount, Transaction::TYPE_INTEREST, $description, $date);

        $tx->createEntry(
            $this->team->accounts()->receivables()->value('id'),
            $date,
            0,
            $amount,
        );

        $tx->createEntry(
            $this->team->accounts()->interest()->value('id'),
            $date,
            $amount,
            0,
        );
    }

    protected function createMainEntry($tx)
    {
        $tx->createEntry(
            $this->team->glAccounts()->receivables()->value('id'),
            $this->invoiced_at,
            $this->isReimbursment() ? $this->total_amount : 0,
            $this->isReimbursment() ? 0 : $this->total_amount,
        );
    }

    protected function createInvoiceDetailsEntries($tx, $withTaxes = false)
    {
        $this->chargeDetails->each(
            fn($invoiceDetail) => $tx->createEntry(
                $invoiceDetail->gl_account_id,
                $this->invoiced_at,
                $this->getInvoiceDetailCredit($invoiceDetail),
                $this->getInvoiceDetailDebit($invoiceDetail),
                null,
                $invoiceDetail->description,
            )
        );

        if (!$withTaxes || !$this->team->tax_accounts_enabled) {
            return;
        }

        $this->team->team->taxes->each(
            fn($tax) => $tx->createOptionalEntry(
                $this->team->accounts()->forTax($tax->id)->value('id'),
                $this->invoiced_at,
                $this->isReimbursment() ? 0 : $this->getAmountForTax($tax->id),
                $this->isReimbursment() ? $this->getAmountForTax($tax->id) : 0,
            )
        );
    }

    protected function getInvoiceDetailCredit($chargeDetail)
    {
        return $this->isReimbursment() ? 0 : $chargeDetail->total_amount_chd;
    }

    protected function getInvoiceDetailDebit($chargeDetail)
    {
        return $this->isReimbursment() ? $chargeDetail->total_amount_chd : 0;
    }

    public function markInitialStatus()
    {
        $this->status = InvoiceStatusEnum::VOIDED;
        $this->approved_by = null;
        $this->approved_at = null;
        $this->save();
    }

    public function markApproved()
    {
        $this->status = InvoiceStatusEnum::APPROVED;
        $this->approved_by = auth()->id();
        $this->approved_at = now();
        $this->save();
    }

    public function markSent()
    {
        if ($this->canMarkSent()) {
            $this->status = InvoiceStatusEnum::SENT;
        }
        $this->sent_by = auth()->id();
        $this->sent_at = now();
        $this->save();
    }

    public function markPayment()
    {
        $this->markPaymentStatus(InvoiceStatusEnum::APPROVED, InvoiceStatusEnum::PARTIALLY_PAID, InvoiceStatusEnum::PAID);
    }

    /* PAST INVOICES */
    public static function getPastInvoices($type)
    {
        return Invoice::with('customer')->where('union_id', currentUnion()->id)
            ->where('invoiced_at', '<', currentUnion()->balance_date)
            ->whereHas('transactions',
                fn($q) => $q->whereHas('entries', fn($q) => $q->where('gl_account_id', GlAccount::usableReceivables()->value('id')))
            )
            ->get();
    }

    public static function createPastInvoice($unit, $invoiceDate, $invoiceNumber, $type = null)
    {
        $invoice = static::getNewInvoice($unit, $invoiceDate, $invoiceNumber, $type);

        $invoice->checkUniqueNumber();

        $invoice->notes = $unit->name.' - '.__('finance.initial-balance-past-invoice');
        $invoice->save();

        return $invoice;
    }

    public static function getNewInvoice($unit, $invoiceDate, $invoiceNumber, $type = null)
    {
        $invoice = new static();
        $invoice->union_id = $unit->union_id;
        $invoice->customer_id = $unit->id;
        $invoice->customer_type = 'unit';
        $invoice->status = InvoiceStatusEnum::DRAFT;
        $invoice->invoice_number = $invoiceNumber;
        $invoice->invoiced_at = $invoiceDate;
        $invoice->due_at = $invoiceDate;
        //$invoice->due_at = $invoiceDate->copy()->addMonths(1);

        $invoice->type = $type ?: static::TYPE_PAYMENT;

        return $invoice;
    }

    public static function createContributionInvoice($unit, $invoiceDate, $budgetId = null)
    {
        $invoice = static::getNewInvoice($unit, $invoiceDate, static::getInvoiceIncrement($unit->union_id, 'COT-'));

        $invoice->budget_id = $budgetId;
        $invoice->notes = $unit->name.' - '.__('finance.contribution-generated');
        $invoice->save();

        return $invoice;
    }

    public function createContributionInvoiceDetail($fund, $amount)
    {
        $this->createRegularInvoiceDetail(
            $this->union->accounts()->income($fund->id)->value('id'),
            $amount,
            __('finance.contribution-to').' '.$fund->name,
            $fund->id
        );
    }

    public function createRegularInvoiceDetail($accountId, $amount, $name, $fundId = null)
    {
        if (!$amount) {
            return;
        }

        $invoiceDetail = new InvoiceDetail();
        $invoiceDetail->name = $name;
        $invoiceDetail->fund_id = $fundId;
        $invoiceDetail->gl_account_id = $accountId;
        $invoiceDetail->quantity = 1;
        $invoiceDetail->price = $amount;
        $this->chargeDetails()->save($invoiceDetail);
    }

    public function createNfsDetail($amount)
    {
        $invoiceDetail = new InvoiceDetail();
        $invoiceDetail->name = __('finance.non-sufficient-funds-fee');
        $invoiceDetail->gl_account_id = $this->chargeDetails()->value('gl_account_id'); //take first account from other details
        $invoiceDetail->quantity = 1;
        $invoiceDetail->price = $amount;
        $this->chargeDetails()->save($invoiceDetail);
    }

    protected function createTransaction($amount, $type = null, $description = null, $date = null)
    {
        $tx = new Transaction();
        $tx->amount = $amount;
        $tx->team_id = $this->team_id;
        $tx->transacted_at = $date ?: $this->invoiced_at;
        $tx->setUserId();
        $tx->type = $type ?: Transaction::TYPE_INVOICE;
        $tx->description = $description ?: $this->notes;
        $this->transactions()->save($tx);

        return $tx;
    }

    public function createPayment($accountId, $paidAt, $paidAmount, $paymentMethod, $description, $writeOff = false)
    {
        if ($this->isReimbursment()) {
            abort(403, __('finance.payment-credit-note-not-allowed'));
        }

        if ($this->canApprove()) {
            $this->markApprovedWithJournalEntries();
        }

        $maxAcceptedAmount = min($paidAmount, $this->due_amount);
        $diffAmount = $paidAmount - $this->due_amount;
        $transactionAmount = $writeOff ? ($diffAmount > 0 ? $maxAcceptedAmount : $this->due_amount) : $paidAmount;

        $paymentTx = $this->createTransaction(
            $transactionAmount,
            Transaction::TYPE_INVOICE_PMT,
            $description ?: (__('finance-invoice-payment').' '.$this->customer_label),
            $paidAt
        );

        $paymentTx->createEntry(
            $accountId,
            $paidAt,
            0,
            $paidAmount,
            $paymentMethod,
        );

        $paymentTx->createEntry(
            $this->mainTransaction->entries()->whereHas('glAccount', fn($q) => $q->receivables())->value('gl_account_id'),
            $paidAt,
            $writeOff ? $transactionAmount : $maxAcceptedAmount,
            0,
        );

        if ($writeOff) {

            $writeOffAmount = round(abs($diffAmount), 2);

            $paymentTx->createEntry(
                GlAccount::usableWriteOff($this->union)->value('id'),
                $paidAt,
                $diffAmount < 0 ? 0 : $writeOffAmount,
                $diffAmount < 0 ? $writeOffAmount : 0,
            );

        } else {

            if ($diffAmount > 0) {

                $paymentTx->createEntry(
                    GlAccount::usableAcompte($this->union)->value('id'),
                    $paidAt,
                    $diffAmount,
                    0,
                );

                //Acompte::addOrDelete($this->customer_id, $diffAmount, $paymentTx->id); //TODO REVIEW
            }

        }

        $this->markPayment();
    }

    public function useAcompteAsPayment($paidAmount, $paidAt, $writeOff = false)
    {
        if ($paidAmount > $this->due_amount) {
            abort(403, __('finance.cannot-overpay-using-advanced-payments'));
        }

        if ($this->canApprove()) {
            $this->markApprovedWithJournalEntries();
        }

        $maxAcceptedAmount = min($paidAmount, $this->due_amount);
        $diffAmount = $this->due_amount - $paidAmount;
        $transactionAmount = $writeOff ? $this->due_amount : $maxAcceptedAmount;

        $paymentTx = $this->createTransaction(
            $transactionAmount,
            Transaction::TYPE_INVOICE_PMT,
            __('finance.transfer-from-advance-payments').' '.$this->customer_label,
            $paidAt
        );

        $paymentTx->createEntry(
            GlAccount::usableAcompte($this->union)->value('id'),
            $paidAt,
            0,
            $maxAcceptedAmount,
            Entry::METHOD_ACOMPTE,
        );

        $paymentTx->createEntry(
            $this->mainTransaction->entries()->whereHas('account', fn($q) => $q->receivables())->value('gl_account_id'),
            $paidAt,
            $transactionAmount,
            0,
        );

        if ($writeOff) {

            $writeOffAmount = round(abs($diffAmount), 2);

            $paymentTx->createEntry(
                GlAccount::usableWriteOff($this->union)->value('id'),
                $paidAt,
                0,
                $writeOffAmount,
            );

        }

        Acompte::addOrDelete($this->customer_id, -$maxAcceptedAmount, $paymentTx->id);

        $this->markPayment();
    }

    public function useCreditNoteAsPayment($creditNote, $paidAt)
    {
        if ($this->canApprove()) {
            $this->markApprovedWithJournalEntries();
        }

        $maxAcceptedAmount = min(abs($creditNote->due_amount), abs($this->due_amount));
        $invRatio = $maxAcceptedAmount/abs($this->due_amount);
        $cnRatio = $maxAcceptedAmount/abs($creditNote->due_amount);

        $paymentTx = $this->createTransaction(
            $maxAcceptedAmount,
            Transaction::TYPE_INVOICE_PMT,
            __('finance.applying-payment-from-credit-note').' '.$creditNote->invoice_number,
            $paidAt
        );

        $paymentTx->createEntry(
            GlAccount::usableAcompte($this->union)->value('id'),
            $paidAt,
            0,
            $maxAcceptedAmount,
            Entry::METHOD_ACOMPTE,
        );

        $paymentTx->createEntry(
            $this->mainTransaction->entries()->whereHas('account', fn($q) => $q->receivables())->value('gl_account_id'),
            $paidAt,
            $maxAcceptedAmount,
            0,
        );

        $this->markPayment();

        $creditNote->updateCreditNoteDueAmount($maxAcceptedAmount);

        $paymentTx->cn_invoice_id = $creditNote->id;
        $paymentTx->save();
    }

    public function offerToUnit($accountId, $date, $amount, $description = null)
    {
        $paymentTx = $this->createTransaction(
            $amount,
            Transaction::TYPE_INVOICE_PMT,
            $description ?: (__('finance.regularisation-payment-for-unit').' '.$this->customer_label),
            $date
        );

        $paymentTx->createEntry(
            $accountId,
            $date,
            0,
            $amount,
        );

        $paymentTx->createEntry(
            $this->customer->union->accounts()->acompte()->value('id'),
            $date,
            $amount,
            0,
        );
    }

    public function createContributionPayment($description = null)
    {
        $this->createPayment(
            $this->union->accounts()->cash()->value('id'),
            $this->invoiced_at,
            $this->due_amount,
            Entry::METHOD_BANK_PAYMENT,
            $description,
        );
    }

    public static function createCeCustomerInvoice($user, $date = null)
    {
        $invoice = new static();
        $invoice->union_id = env('FINANCE_ADMIN_UNION_ID');
        $invoice->customer_id = $user->id;
        $invoice->customer_type = 'user';
        $invoice->status = InvoiceStatusEnum::APPROVED;
        $invoice->invoice_number = static::getUserIncrement($user->id);
        $invoice->invoiced_at = $date ?: date('Y-m-d');
        $invoice->notes = $user->personalTeam()->name.' - '.__('finance.condoedge-subscription');
        $invoice->due_at = $date ?: date('Y-m-d');
        $invoice->save();

        return $invoice;
    }

    public static function getUserIncrement($userId)
    {
        return 'INV-'.sprintf('%03d', $userId).'-'.sprintf('%06d', static::where('customer_type', 'user')->where('customer_id', $userId)->count() + 1);
    }

    public function createCustomerInvoiceDetail($union, $planService, $qty, $amount)
    {
        $invoiceDetail = new InvoiceDetail();
        $invoiceDetail->name = $union->name.' - '.$planService->display;
        $invoiceDetail->description = $planService->description;
        $invoiceDetail->gl_account_id = $this->union->accounts()->income()->value('id');
        $invoiceDetail->quantity = $qty;
        $invoiceDetail->price = $amount;
        $this->chargeDetails()->save($invoiceDetail);

        $invoiceDetail->taxes()->sync($this->union->team->taxes->pluck('id'));
    }

    public function sendEmail($messageText = null, $sendMethod = 'queue')
    {
        $invoiceSent = false;

        foreach($this->customer->owners as $owner) {

            if ($owner->mainEmail()) {
                Mail::to($owner)->{$sendMethod}(
                    new ContributionNotification($owner, $this, $messageText)
                );

                $invoiceSent = true;
            }
        }

        if ($invoiceSent) {
            $this->markSent();
        }

        return $invoiceSent;
    }

    /* ELEMENTS */
    public function statusBadge()
    {
        return _Pill($this->status_label)
            ->class($this->color);
    }
}
