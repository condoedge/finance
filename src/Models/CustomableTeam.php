<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Models\Traits\CanBeFinancialCustomer;
use Condoedge\Utils\Models\Model;

// Not used for queries, just a service model to create customers from teams
class CustomableTeam extends Model implements CustomableContract
{
    use CanBeFinancialCustomer;
    use \Condoedge\Utils\Models\ContactInfo\Maps\MorphManyAddresses;

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
        return __('finance-team2');
    }

    public static function getOptionsForCustomerForm($search = null)
    {
        $query = static::query();

        if ($search) {
            $query->where('team_name', 'like', wildcardSpace($search));
        }

        return $query->pluck('team_name', 'id');
    }
}
