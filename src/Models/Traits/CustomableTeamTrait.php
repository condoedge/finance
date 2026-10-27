<?php

namespace Condoedge\Finance\Models\Traits;

use Condoedge\Utils\Facades\TeamModel;
use Illuminate\Support\Collection;

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

    public function scopeInvoiceRecipientsWithin($query, int $teamId)
    {
        return $query->whereIn($this->qualifyColumn('id'), TeamModel::findOrFail($teamId)->getDescendants());
    }

    public static function searchForInvoiceRecipient(string $search, int $teamId): Collection
    {
        return static::query()->invoiceRecipientsWithin($teamId)
            ->where('team_name', 'like', wildcardSpace($search))
            ->orderBy('team_name')
            ->take(10)
            ->get();
    }
}
