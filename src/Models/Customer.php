<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Events\CustomerCreated;
use Condoedge\Finance\Models\Dto\CreateOrUpdateCustomerDto;
use Condoedge\Finance\Models\Dto\CreateCustomerFromCustomable;
use Condoedge\Utils\Facades\GlobalConfig;
use Illuminate\Support\Facades\DB;
use Condoedge\Utils\Models\ContactInfo\Maps\Address;

/**
 * Class Invoice
 * 
 * @package Condoedge\Finance\Models
 * 
 * This table shouldn't be called in the app if you're not touching any financial part. You must have 
 * your own Customer class into your app implementing CustomableContract.
 * 
 * @property int $id
 * @property string $name
 * @property int $team_id 
 * @property float $customer_due_amount @CALCULATED BY calculate_customer_due() - Remaining amount to be paid
 * @property int|null $default_billing_address_id Foreign key to fin_addresses
 * @property int|null $default_payment_type_id Foreign key to fin_payment_types
 * @property int|null $default_tax_group_id Foreign key to fin_taxes_groups
 */
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
        return $this->morphTo('customable', 'customable_type', 'customable_id', 'id');
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
    public static function createOrEditFromDto(CreateOrUpdateCustomerDto $dto)
    {
        if (isset($dto->id)) {
            $customer = self::findorFail($dto->id);
            $customer->name = $dto->name;
            $customer->save();

            return $customer;
        }

        $customer = new self;
        $customer->name = $dto->name;
        $customer->team_id = $dto->team_id ?? currentTeamId();
        $customer->save();

        Address::createMainForFromRequest($customer, $dto->address->toArray());

        return $customer;
    }

    public static function createOrEditFromCustomable(CreateCustomerFromCustomable $dto)
    {
        $customer = $dto->customable->upsertCustomerFromThisModel();

        if ($dto->address) {
            Address::createMainForFromRequest($customer, $dto->address->toArray());
        }

        return $customer;
    }

    public function setDefaultAddress($addressId)
    {
        $this->default_billing_address_id = $addressId;
        $this->save();
    }

    public function setPrimaryBillingAddress($id)
    {
        $this->default_billing_address_id = $id;
        $this->save();
    }

    public function setPrimaryShippingAddress($id)
    {
        $this->default_billing_address_id = $id;
        $this->save();
    }

    public function fillInvoiceForCustomer(Invoice $initialInvoice)
    {
        if (!$this->default_billing_address_id) {
            throw new \Illuminate\Database\Eloquent\RelationNotFoundException(__('translate.customer-address-not-set'));
        }

        $invoice = $initialInvoice;
        $invoice->customer_id = $this->id;
        // $invoice->tax_group_id = $this->defaultAddress->tax_group_id ?? GlobalConfig::getOrFail('default_tax_group_id');
        $invoice->payment_type_id = $this->default_payment_type_id ?? GlobalConfig::getOrFail('default_payment_type_id');
    }

    // SCOPES

    /* INTEGRITY */
    public static function columnsIntegrityCalculations()
    {
        return [
            'customer_due_amount' => DB::raw('calculate_customer_due(fin_customers.id)'),
        ];
    }

    /* ELEMENTS */
}
