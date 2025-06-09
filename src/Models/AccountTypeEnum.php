<?php

namespace Condoedge\Finance\Models;

enum AccountTypeEnum: int
{
    use \Kompo\Models\Traits\EnumKompo;

    case ASSET = 1;
    case LIABILITY = 2;
    case EQUITY = 3;
    case REVENUE = 4;
    case EXPENSE = 5;

    /**
     * Get human-readable label for the account type
     */
    public function label(): string
    {
        return match($this) {
            self::ASSET => 'Asset',
            self::LIABILITY => 'Liability',
            self::EQUITY => 'Equity',
            self::REVENUE => 'Revenue',
            self::EXPENSE => 'Expense',
        };
    }    
}