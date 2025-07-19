<?php

namespace Condoedge\Finance\Models\Traits;

trait CustomableTeamTrait
{
    use CanBeFinancialCustomer;
    use \Condoedge\Utils\Models\ContactInfo\Maps\MorphManyAddresses;

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
