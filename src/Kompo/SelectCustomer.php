<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Customer;
use Condoedge\Utils\Kompo\Common\Form;

class SelectCustomer extends Form
{
    protected $teamId;
    protected $defaultId;

    public $id = 'select-customer';

    public function created()
    {
        $this->teamId = $this->prop('team_id');
        $this->defaultId = $this->prop('default_id');
    }

    public function render()
    {
        return _Select('finance-invoiced-to')->name('customer_id', false)->default($this->defaultId)->class('!mb-0')
            ->options(Customer::forTeam($this->teamId)->pluck('name', 'id'));
    }

    public function rules()
    {
        return [
            'customer_id' => 'required|exists:fin_customers,id',
        ];
    }
}