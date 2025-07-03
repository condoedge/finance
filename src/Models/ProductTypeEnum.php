<?php

namespace Condoedge\Finance\Models;

enum ProductTypeEnum: int
{
    use \Condoedge\Utils\Models\Traits\EnumKompo;

    case PRODUCT_COST = 1;
    case TEAM_LEVEL_COMMISSION = 5;
    case SERVICE_COST = 10;
    case REBATE = 15;

    // case REBATE_MORE_THAN_ONE_CHILD = 16;

    public function label()
    {
        return match ($this) {
            self::TEAM_LEVEL_COMMISSION => __('finance.team-level-commission'),
            self::SERVICE_COST => __('finance.service'),
            self::PRODUCT_COST => __('finance.product'),
            self::REBATE => __('finance.rebate'),
        };
    }

    public function visibleInSelects()
    {
        return match ($this) {
            self::TEAM_LEVEL_COMMISSION => false,
            default => true,
        };
    }

    public function getValue(Product $product)
    {
        return match ($this) {
            self::TEAM_LEVEL_COMMISSION => $product->product_cost,
            self::SERVICE_COST => $product->product_cost,
            self::PRODUCT_COST => $product->product_cost,
            self::REBATE => -$product->product_cost,
        };
    }

    public function getCommissionValue(Product $product)
    {
        return match ($this) {
            self::TEAM_LEVEL_COMMISSION => $product->product_cost,
            default => 0,
        };
    }

    public function countInTotal()
    {
        return match ($this) {
            self::TEAM_LEVEL_COMMISSION => false,
            default => true,
        };
    }

    public function isCommission()
    {
        return match ($this) {
            self::TEAM_LEVEL_COMMISSION => true,
            default => false,
        };
    }

    /**
     * @return \Closure(): bool
     */
    public function callbackAppliesToLogic()
    {
        return match ($this) {
            default => fn () => true,
        };
    }

    public static function getTypesCountInTotal()
    {
        return collect(self::cases())->filter(fn ($case) => $case->countInTotal());
    }

    public static function getTypesCommissions()
    {
        return collect(self::cases())->filter(fn ($case) => $case->isCommission());
    }
}
