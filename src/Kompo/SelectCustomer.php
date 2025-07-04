<?php

namespace Condoedge\Finance\Kompo;

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
        return teamCustomersSelect($this->teamId, $this->defaultId)->class('!mb-0');
    }

    public function rules()
    {
        return [
            'customer_id' => 'required|exists:fin_customers,id',
        ];
    }
}
