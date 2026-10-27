<?php

namespace Condoedge\Finance\Models\Traits;

use Condoedge\Finance\Facades\CustomerModel;
use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Models\CustomableContract;
use Condoedge\Finance\Models\Dto\Invoices\CreateInvoiceDto;
use Illuminate\Support\Collection;

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

    /* INVOICE RECIPIENT */

    /** No perimeter by default: an app that knows its hierarchy narrows it. */
    public function scopeInvoiceRecipientsWithin($query, int $teamId)
    {
        return $query;
    }

    public static function searchForInvoiceRecipient(string $search, int $teamId): Collection
    {
        return static::query()->invoiceRecipientsWithin($teamId)
            ->whereKey(collect(static::getOptionsForCustomerForm($search))->keys())
            ->take(10)
            ->get();
    }

    public function invoiceRecipientsFor(int $teamId): Collection
    {
        return collect([$this]);
    }

    public function invoiceRecipientName(): string
    {
        return (string) $this->fillCustomerFromThisModel(CustomerModel::newModelInstance())->name;
    }

    public function invoiceRecipientLabel(?CustomableContract $billed)
    {
        return _Rows(
            _Html($this->invoiceRecipientName()),
            $billed && !$billed->is($this)
                ? _Html(__('finance-billed-to', ['name' => $billed->invoiceRecipientName()]))->class('text-xs text-gray-500')
                : null,
            $billed ? null : _Html('finance-no-one-to-bill')->class('text-xs text-danger'),
        );
    }

    abstract public function fillCustomerFromThisModel($customer);
}
