<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\CustomerModel;
use Condoedge\Utils\Kompo\Common\Table;

class FinantialCustomersTable extends Table
{
    public function top()
    {
        return _FlexBetween(
            _Input('search')->name('name')->filter(),
        );
    }

    public function query()
    {
        return CustomerModel::forTeam(currentTeamId())->orderBy('customer_due_amount', 'desc');
    }

    public function headers()
    {
        return [
            _Th('finance.customer-name')->sort('name'),
            _Th('finance.customer-due-amount')->sort('customer_due_amount'),
        ];
    }
    
    public function render($customer)
    {
        return _TableRow(
            _Html($customer->name),
            _Html($customer->customer_due_amount),
        )->href('finantial-customers.page', [
            'id' => $customer->id,
        ]);
    }
}