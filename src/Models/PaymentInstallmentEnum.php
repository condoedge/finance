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
            self::ONE_TIME => __('finance.one-time'),
            self::TWO_TIMES => __('finance.two-times'),
            self::THREE_TIMES => __('finance.three-times'),
            self::FOUR_TIMES => __('finance.four-times'),
        };
    }

    public function labelWithAmount($amount)
    {
        return match($this) {
            self::ONE_TIME => __('finance.one-time-values', ['amount' => $this->getPartAmount($amount)]),
            self::TWO_TIMES => __('finance.two-times-values', ['amount' => $this->getPartAmount($amount)]),
            self::THREE_TIMES => __('finance.three-times-values', ['amount' => $this->getPartAmount($amount)]),
            self::FOUR_TIMES => __('finance.four-times-values', ['amount' => $this->getPartAmount($amount)]),
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

    public function getNextDate($date)
    {
        return match($this) {
            default => $date->addMonths($this->getTimes() - 1),
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