<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Models\Transaction;
use Kompo\Auth\Models\Model;
use App\Models\User;

abstract class Charge extends Model
{
    use \Kompo\Auth\Models\Teams\BelongsToTeamTrait;
    use \Kompo\Auth\Models\Files\MorphManyFilesTrait;
    use \Kompo\Auth\Models\Tags\MorphToManyTagsTrait;

    protected $casts = [];
    protected $toExtendCasts = [];
    protected static $mainTransactionTypes = [];

    protected const ITEMS_RELATION = 'to_override';

    public const TYPE_PAYMENT = 1;
    public const TYPE_REIMBURSMENT = 2;

    // This is a fix to the overriden casts when we're extending the model
    public function __construct()
    {
        parent::__construct();

        $this->casts = array_merge($this->casts, $this->toExtendCasts);
    }

    public function save(array $options = [])
    {
        $this->setTeamId();

        parent::save($options);
    }

    /* RELATIONSHIPS */
    public function chargeDetails()
    {
        return $this->hasMany(ChargeDetail::class);
    }

    public function transaction()
    {
        return $this->hasOne(Transaction::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function mainTransaction()
    {
        return $this->hasOne(Transaction::class)->whereIn('type', static::$mainTransactionTypes)->notVoid()->orderByDesc('id');
    }

    public function payments()
    {
        return $this->transactions()->notVoid()->isPaymentType();
    }

    public function interests()
    {
        return $this->transactions()->notVoid()->isInterestType();
    }

    public function lastPayment()
    {
        return $this->hasOne(Transaction::class)->isPaymentType()->notVoid()->orderByDesc('id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function sentBy()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /* SCOPES */
    public function scopeIsDirect($query)
    {
        $query->where('type', static::TYPE_PAYMENT);
    }

    public function scopeIsReimbursment($query)
    {
        $query->where('type', static::TYPE_REIMBURSMENT);
    }

    /* CALCULATED FIELDS */
    public function formOpenable($union = null)
    {
        return true;
        return auth()->user()->can($this->id ? 'update' : 'create', $this) && !$this->isFromInitialBalances($union);
    }

    public function isFromInitialBalances($union = null)
    {
        $union = $union ?: $this->union;

        return $this->{static::DATE_COLUMN} && ($this->{static::DATE_COLUMN} < $union->balance_date);
    }

    public function isReimbursment()
    {
        return $this->type == static::TYPE_REIMBURSMENT;
    }

    public function sign()
    {
        return $this->isReimbursment() ? -1 : 1;
    }

    public static function getIncrement($prefix, $teamId = null)
    {
        $maxNumber = static::where('team_id', $teamId ?: currentTeam()->id)
            ->whereRaw('LEFT('.static::NUMBER_COLUMN.','.strlen($prefix).') = ?', [$prefix])
            ->whereRaw('LENGTH('.static::NUMBER_COLUMN.') = ?', [strlen($prefix)+6])
            ->max(static::NUMBER_COLUMN);

        $nextNumber = (int) substr($maxNumber, -6) + 1;

        $number = $prefix.sprintf('%06d', $nextNumber);
        for ($i=0; $i < 10; $i++) {
            if (!static::where('team_id', $teamId ?: currentTeam()->id)->where(static::NUMBER_COLUMN, $number)->count()) {
                return $number;
            } else {
                $nextNumber += 1;
                $number = $prefix.sprintf('%06d', $nextNumber);
            }
        }

        return $number;
    }

    public function getAmountForTax($taxId)
    {
        $this->loadMissing('chargeDetails.taxes');

        return $this->chargeDetails->map(fn($item) => $item->getAmountForTax($taxId))->sum();
    }

    public function dateModified($dateName)
    {
        return ($this->{$dateName}?->format('Y-m-d') != substr(request($dateName), 0, 10)) ||
            ($this->due_at?->format('Y-m-d') != substr(request('due_at'), 0, 10));
    }

    public function detailsModified()
    {
        return $this->chargeDetails->filter(function($item, $key) {
            if(!key_exists($key, request('chargeDetails'))) {
                return false;
            }

            $input = request('chargeDetails')[$key];

            $tax1 = $item->taxes->pluck('id')->toArray();
            $tax2 = $input['taxes'] ?: [];

            return ($item->gl_account_id != $input['gl_account_id']) ||
                ($item->quantity != $input['quantity_chd']) ||
                ($item->price != $input['price_chd']) ||
                (count(array_diff(array_merge($tax1, $tax2), array_intersect($tax1, $tax2))) !== 0);

        })->count();
    }

    /* ATTRIBUTES */
    public function getAmountAttribute()
    {
        return $this->chargeDetails()->sum('pretax_amount_chd');
    }

    public function getTaxAmountAttribute()
    {
        return $this->chargeDetails()->sum('tax_amount_chd');
    }

    public function getTotalAmountAttribute()
    {
        return $this->chargeDetails()->sum('total_amount_chd');
    }

    public function getDueAmountAttribute()
    {
        if ($this->isReimbursment() && !is_null($this->calc_due_amount)) { //TODO move all due/total amounts to charges tables
            return $this->calc_due_amount;
        }

        $this->load('payments');

        return $this->sign() * max(0, round($this->total_amount - $this->payments->sum('amount'), 2)); //remove overpaid
    }

    public function getRealDueAmountAttribute() //faster also, we preload relations before
    {
        if ($this->isReimbursment() && !is_null($this->calc_due_amount)) { //TODO move all due/total amounts to charges tables
            return $this->calc_due_amount;
        }

        return $this->sign() * round($this->total_amount - $this->payments->sum('amount'), 2); //use this to get overpayment
    }

    public function getStatusLabelAttribute(): string
    {
        return static::statuses()[$this->status] ?? '';
    }

    public function getColorAttribute(): string
    {
        return static::colors()[$this->status] ?? '';
    }

    abstract public static function statuses();

    abstract public static function colors();

    /* ACTIONS */
    abstract public function markInitialStatus();

    public function markPaymentStatus($notPaidStatus, $partialStatus, $paidStatus)
    {
        $due = abs($this->due_amount);
        $total = $this->total_amount;

        $this->status = (abs($due) < 0.01) ? $paidStatus : ((abs($total - $due) < 0.01) ? $notPaidStatus : $partialStatus);
        $this->save();
    }

    public function updateCreditNoteDueAmount($amount)
    {
        $this->calc_due_amount = -1 * (abs($this->due_amount) - $amount);

        $this->markPayment();
    }

    public function delete()
    {
        $this->{static::ITEMS_RELATION}->each->delete();

        $this->transactions->each->delete();

        parent::delete();
    }

    public function checkUniqueNumber()
    {
        $numberName = static::NUMBER_COLUMN;

        $newNumber = request($numberName);

        if(!$this->id && Bill::where('team_id', currentTeamId())->where('bill_number', $newNumber)->count()) {
            throwValidationError($numberName, __('finance.there-already-has-a-bill-with-the-number').' '.$newNumber.' '.__('finance.in-the-system-choose-another-one'));
        }

        if(!$this->id && Invoice::where('team_id', currentTeamId())->where('invoice_number', $newNumber)->count()) {
            throwValidationError($numberName, __('finance.there-already-has-a-bill-with-the-number').' '.$newNumber.' '.__('finance.in-the-system-choose-another-one'));
        }

    }

    /* ELEMENTS */
    public function approvalEls()
    {
        return _Flex2(
            $this->approvedByLabel()->icon('icon-check'),
            $this->approved_at ? _Flex2(
                _Html('finance-on-le'),
                _HtmlDate($this->approved_at)->class('font-bold'),
            ) : null
        );
    }

    public function approvedByLabel()
    {
        return _Html(__('finance-approved-by') . '<b> ' . $this->approvedBy->name. '</b>');
    }

    public function sentEls()
    {
        if (!$this->sentBy) {
            return;
        }

        return _Flex2(
            _Html(__('finance-sent-by') . ' ' . $this->sentBy->name)->icon('icon-check'),
            $this->sent_at ? _Flex2(
                _Html('on-le'),
                _DateStr($this->sent_at)->class('font-bold'),
            ) : null
        );
    }

    public function attachedFilesBox()
    {
        if (!$this->files->count()) {
            return;
        }

        return _Rows(
            _TitleMini('file-attached-files')->class('uppercase mb-2'),
            _Rows(
                _Flex(
                    $this->files->map(function($file){
                        return $file->fileThumbnail();
                    })
                )->class('flex-wrap')
            )->class('dashboard-card mb-6 p-4 pb-0'),
        );
    }
}
