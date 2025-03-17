<?php

namespace Condoedge\Finance\Models;

class CustomerPayment extends AbstractMainFinanceModel
{
    protected $table = 'fin_customer_payments';

    public static function checkIntegrity($ids = null): void
    {
        // TODO: Implement checkIntegrity() method.
    }
}