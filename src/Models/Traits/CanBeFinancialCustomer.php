<?php

namespace Condoedge\Finance\Models\Traits;

use Condoedge\Finance\Facades\CustomerModel;
use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Models\Dto\Invoices\CreateInvoiceDto;

trait CanBeFinancialCustomer
{
    public static function bootCanBeFinancialCustomer()
    {
        static::updated(function ($model) {
            if ($model->hasCustomer()) {
                $model->updateCustomersFields();
            }
        });
    }

    public function customers()
    {
        return $this->hasMany(CustomerModel::getClass(), 'customable_id', 'id')
            ->where('customable_type', $this->getMorphClass());
    }

    public function hasCustomer()
    {
        return \Cache::remember(
            'has_customer_' . $this->getMorphClass() . '_' . $this->id,
            60 * 60 * 24,
            function () {
                return $this->customers()->exists();
            }
        );
    }

    public function createInvoiceForThisModel()
    {
        $customer = $this->upsertCustomerFromThisModel();

        InvoiceService::createInvoice(new CreateInvoiceDto([
            'customer_id' => $customer->id,
        ]));
    }

    public function updateCustomersFields()
    {
        $this->customers()->each(function ($customer) {
            $this->fillCustomerFromThisModel($customer);
            $customer->default_billing_address_id = $this->getFirstValidAddress()?->id;
            $customer->save();
        });
    }

    public function upsertCustomerFromThisModel($teamId = null)
    {
        $teamId = $teamId ?? $this->team_id ?? currentTeamId();
        $customer = $this->customers()
            ->where('team_id', $teamId)
            ->first();

        if (!$customer) {
            $customer = CustomerModel::newModelInstance();
            $customer->team_id = $teamId;
        }

        $this->fillCustomerFromThisModel($customer);
        $customer->default_billing_address_id = $this->getFirstValidAddress()?->id;

        if ($customer->isDirty()) {
            $customer->customable_id = $this->id;
            $customer->customable_type = $this->getMorphClass();
            $customer->save();
        }

        return $customer;
    }

    abstract public function fillCustomerFromThisModel($customer);
}
