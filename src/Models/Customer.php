<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Events\CustomerCreated;
use Condoedge\Utils\Facades\GlobalConfig;
use Illuminate\Support\Facades\DB;
use Condoedge\Utils\Models\ContactInfo\Maps\Address;

class Customer extends AbstractMainFinanceModel
{
    use \Condoedge\Utils\Models\Traits\BelongsToTeamTrait;
    use \Condoedge\Utils\Models\ContactInfo\Maps\MorphManyAddresses;

    public static function boot()
    {
        static::updated(function ($model) {
            if ($customable = $model->customable()->first()) {
                $customable->updateFromCustomer($model);
            }
        });

        parent::boot();
    }
    
    protected $table = 'fin_customers';

    protected function getCreatedEventClass()
    {
        return CustomerCreated::class;
    }
    
    /* RELATIONSHIPS */
    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'customer_id');
    }

    public function defaultAddress()
    {
        return $this->belongsTo(Address::class, 'default_billing_address_id');
    }

    public function customable()
    {
        return $this->morphTo();
    }

    /* ATTRIBUTES */

    /* CALCULATED FIELDS */
    public static function getCustomables()
    {
        $customables = collect(config('kompo-finance.customable_models'));

        $customables->each(function ($customable) {
            if (!in_array(CustomableContract::class, class_implements($customable))) {
                throw new \Exception(__('translate.customable-model-must-implement', ['model' => $customable]));
            }
        });

        return $customables;
    }

    /* SCOPES */

    /* ACTIONS */
    public function setDefaultAddress($addressId)
    {
        $this->default_billing_address_id = $addressId;
        $this->save();
    }

    public function fillInvoiceForCustomer(Invoice $initialInvoice)
    {
        if (!$this->default_billing_address_id) {
            throw new \Illuminate\Database\Eloquent\RelationNotFoundException(__('translate.customer-address-not-set'));
        }

        $invoice = $initialInvoice;
        $invoice->customer_id = $this->id;
        $invoice->tax_group_id = $this->defaultAddress->tax_group_id ?? GlobalConfig::getOrFail('default_tax_group_id');
        $invoice->payment_type_id = $this->default_payment_type_id ?? GlobalConfig::getOrFail('default_payment_type_id');
    }

    /* INTEGRITY */
    public static function checkIntegrity($ids = null): void
    {
        DB::table('fin_customers')
            ->when($ids, function ($query) use ($ids) {
                return $query->whereIn('id', $ids);
            })
            ->update([
                'customer_due_amount' => DB::raw('calculate_customer_due(fin_customers.id)'),
            ]);
    }

    /* ELEMENTS */
}
