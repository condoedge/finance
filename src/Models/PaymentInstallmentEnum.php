<?php

namespace Condoedge\Finance\Models;

enum PaymentInstallmentEnum: int
{
    use \Kompo\Auth\Models\Traits\EnumKompo;
    
    case ONE_TIME = 1;
    case TWO_TIMES = 2;
    case THREE_TIMES = 3;
    case FOUR_TIMES = 4;

    public function label()
    {
        return match($this) {
            self::ONE_TIME => __('translate.finance.one-time'),
            self::TWO_TIMES => __('translate.finance.two-times'),
            self::THREE_TIMES => __('translate.finance.three-times'),
            self::FOUR_TIMES => __('translate.finance.four-times'),
        };
    }

    public function labelWithAmount($amount)
    {
        return match($this) {
            self::ONE_TIME => __('translate.with-values.finance.one-time', ['amount' => $this->getPartAmount($amount)]),
            self::TWO_TIMES => __('translate.with-values.finance.two-times', ['amount' => $this->getPartAmount($amount)]),
            self::THREE_TIMES => __('translate.with-values.finance.three-times', ['amount' => $this->getPartAmount($amount)]),
            self::FOUR_TIMES => __('translate.with-values.finance.four-times', ['amount' => $this->getPartAmount($amount)]),
        };
    }

    public static function optionsWithLabelsAmount($amount)
    {
        return collect(self::cases())->filter(fn($case) => $case->visibleInSelects())->mapWithKeys(fn($enum) => [
            $enum->value => $enum->labelWithAmount($amount),
        ]);
    }
    
    public function getPartAmount($totalAmount, $number = 0)
    {
        return match($this) {
            default => $totalAmount / $this->getTimes(),
        };
    }

    public function getTimes()
    {
        return match($this) {
            self::ONE_TIME => 1,
            self::TWO_TIMES => 2,
            self::THREE_TIMES => 3,
            self::FOUR_TIMES => 4,
        };
    }
}