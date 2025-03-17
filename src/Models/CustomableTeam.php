<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Models\Traits\CanBeFinancialCustomer;
use Kompo\Auth\Models\Teams\Team;

// Not used for queries, just a service model to create customers from teams
class CustomableTeam extends Team implements CustomableContract
{
    use CanBeFinancialCustomer;

    protected $table = 'teams';

    public function fillCustomerFromThisModel($customer)
    {
        $customer->name = $this->team_name;

        return $customer;
    }

    public function updateFromCustomer($customer)
    {
        $this->team_name = $customer->name;

        if ($this->isDirty()) {
            $this->save();
        }
    }

    public static function getVisualName()
    {
        return 'translate.team';
    }

    public static function getOptionsForCustomerForm()
    {
        return static::pluck('team_name', 'id');
    }
}