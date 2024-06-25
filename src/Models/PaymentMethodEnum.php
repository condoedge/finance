<?php

namespace Condoedge\Finance\Models;

enum PaymentMethodEnum: int 
{
    use \Kompo\Auth\Models\Traits\EnumKompo;
    
    case CREDIT_CARD_ONLINE = 1;
    case DEBIT_CARD_ONLINE = 2;
    case INTERAC_TRANSFER = 3;
    case CASH = 4;
    case CHEQUE = 5;
    case BANK_TRANSFER = 6;
    case CREDIT_CARD_OFFLINE = 7;

    public function offline()
    {
        return match($this) {
            self::CASH, self::CHEQUE, self::BANK_TRANSFER, self::CREDIT_CARD_OFFLINE => true,
            default => false,
        };
    }

    public function online()
    {
        return !$this->offline();
    }

    public function label()
    {
        return match($this) {
            self::CREDIT_CARD_ONLINE => __('finance.credit-card-online'),
            self::DEBIT_CARD_ONLINE => __('finance.debit-card-online'),
            self::INTERAC_TRANSFER => __('finance.interac-transfer-online'),
            self::CASH => __('finance.cash'),
            self::CHEQUE => __('finance.cheque'),
            self::BANK_TRANSFER => __('finance.bank-transfer'),
            self::CREDIT_CARD_OFFLINE => __('finance.credit-card-offline'),
        };
    }
}