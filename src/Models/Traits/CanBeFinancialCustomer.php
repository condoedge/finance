<?php

namespace Condoedge\Finance\Models\Traits;

use Condoedge\Finance\Facades\CustomerModel;
use Illuminate\Database\Eloquent\ModelNotFoundException;

trait CanBeFinancialCustomer
{
    public static function bootCanBeFinancialCustomer()
    {
        static::updated(function ($model) {
            if ($model->customer_id) {
                $model->upsertCustomerFromThisModel();
            }
        });
    }

    public function customer()
    {
        return $this->belongsTo(CustomerModel::getClass(), 'customer_id');
    }

    public function createInvoiceForThisModel() 
    {
        if (!$this->customer) {
            throw new ModelNotFoundException(__('translate.create-customer-first'));
        }

        $this->customer->createInvoiceForCustomer();
    }

    public function upsertCustomerFromThisModel($teamId = null)
    {
        $customer = $this->customer()->first();

        if (!$customer) {
            $customer = CustomerModel::newModelInstance();
            $customer->team_id = $teamId ?? $this->team_id ?? currentTeamId();
        }

        $this->fillCustomerFromThisModel($customer);
        $customer->default_billing_address_id = $this->getFirstValidAddress()?->id;

        if ($customer->isDirty()) {
            $customer->customable_id = $this->id;
            $customer->customable_type = $this->getMorphClass();
            $customer->save();
        }

        if ($this->customer_id != $customer->id) {
            $this->setCustomerId($customer->id);
        }

        return $customer;
    }

    public function setCustomerId($customerId)
    {
        $this->customer_id = $customerId;
        $this->save();
    }

    abstract public function fillCustomerFromThisModel($customer);
}