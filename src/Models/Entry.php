<?php

namespace Condoedge\Finance\Models;

use Kompo\Auth\Models\Model;

class Entry extends Model
{
    use \Condoedge\Finance\Models\BelongsToGlAccountTrait;

    protected $casts = [
        'transacted_at' => 'datetime',
    ];

    public const METHOD_JOURNAL_ENTRY = 1;
    public const METHOD_BANK_PAYMENT = 2;
    public const METHOD_CASH = 3;
    public const METHOD_CHEQUE = 4;
    public const METHOD_CREDIT_CARD = 5;
    public const METHOD_PAYPAL = 6;
    public const METHOD_OTHER = 7;
    public const METHOD_ACOMPTE = 8;
    public const METHOD_INTERAC_TRANSFER = 9;


    public function save(array $options = [])
    {
        if (!$this->payment_method) {
            $this->payment_method = static::METHOD_JOURNAL_ENTRY;
        }

        parent::save();
    }

    /* RELATIONSHIPS */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    /* SCOPES */
    public function scopeNotVoid($query)
    {
        $query->whereHas('transaction', fn($q) => $q->notVoid());
    }

    /* ATTRIBUTES */
    public function getMethodLabelAttribute()
    {
        return static::paymentMethods()[$this->payment_method];
    }

    /* CALCULATED FIELDS */
    public static function paymentMethods()
    {
        return [
            static::METHOD_JOURNAL_ENTRY => __('finance.journal-entry'),
            static::METHOD_BANK_PAYMENT => __('finance.bank-payment'),
            static::METHOD_INTERAC_TRANSFER => __('finance.interac-payment'),
            static::METHOD_CASH => __('finance.cash'),
            static::METHOD_CHEQUE => __('finance.cheque'),
            static::METHOD_CREDIT_CARD => __('finance.credit-card'),
            static::METHOD_PAYPAL => __('finance.paypal'),
            static::METHOD_OTHER => __('finance.other'),
            static::METHOD_ACOMPTE => __('finance.advance-payment'),
        ];
    }

    public static function usablePaymentOptions()
    {
        return collect(static::paymentMethods())
            ->filter(fn($label, $key) => in_array($key, [
                static::METHOD_BANK_PAYMENT,
                static::METHOD_INTERAC_TRANSFER,
                static::METHOD_CASH,
                static::METHOD_CHEQUE,
                static::METHOD_CREDIT_CARD,
                static::METHOD_PAYPAL,
                static::METHOD_OTHER,
                static::METHOD_ACOMPTE,
            ]) );
    }

    /* ELEMENTS */
    public static function paymentMethodsSelect($relatedToModel = true)
    {
        $panelId = uniqid();

        return _Rows(
            _Select('finance.payment-method')->name('payment_method', $relatedToModel)
                ->options(
                    static::usablePaymentOptions()
                )->default(
                    static::METHOD_BANK_PAYMENT
                )->class('mb-0'),
            _Rows(
                _Input('finance.payment-number')->name('payment_number')
                    ->placeholder('finance.cheque-or-transaction')->class('mt-2 mb-0'),
            )
        );
    }
}
