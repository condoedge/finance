<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\CustomerModel;
use Condoedge\Utils\Kompo\Common\Form;

class FinantialCustomerPage extends Form
{
    public $model = CustomerModel::class;

    public function render()
    {
        return _Rows(
            _Html($this->model->name)->class('text-xl font-semibold mb-4'),
            _CardLevel1(
                _Html('due-amount'),
                _FinanceCurrency($this->model->customer_due_amount),
            )->class('text-white')->p4(),

            new FinantialCustomerPayments([
                'customer_id' => $this->model->id,
            ]),
        );
    }
}