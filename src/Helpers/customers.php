<?php

use Condoedge\Finance\Models\Customer;

if (!function_exists('teamCustomersSelect')) {
    function teamCustomersSelect($teamId = null, $customerId = null)
    {
        return _Select('finance-invoiced-to')->name('customer_id')->default($customerId)
            ->options(Customer::forTeam($teamId ?? currentTeamId())->pluck('name', 'id'));
    }
}
