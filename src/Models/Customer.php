<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Casts\SafeDecimalCast;
use Condoedge\Finance\Events\CustomerCreated;
use Condoedge\Finance\Facades\CustomerService;
use Condoedge\Utils\Facades\TeamModel;
use Condoedge\Utils\Models\ContactInfo\Maps\Address;
use Illuminate\Support\Facades\DB;

/**
 * Class Customer
 *
 * @package Condoedge\Finance\Models
 *
 * This table shouldn't be called in the app if you're not touching any financial part. You must have
 * your own Customer class into your app implementing CustomableContract.
 *
 * @property int $id
 * @property string $name
 * @property ?string $email
 * @property ?string $phone
 * @property int $team_id
 * @property \Condoedge\Finance\Casts\SafeDecimal $customer_due_amount @CALCULATED BY calculate_customer_due() - Remaining amount to be paid
 * @property int|null $default_billing_address_id Foreign key to fin_addresses
 * @property int|null $default_payment_method_id Foreign key to fin_payment_methods
 * @property int|null $default_tax_group_id Foreign key to fin_taxes_groups
 */
class Customer extends AbstractMainFinanceModel
{
    use \Condoedge\Utils\Models\Traits\BelongsToTeamTrait;
    use \Condoedge\Utils\Models\ContactInfo\Maps\MorphManyAddresses;

    protected $casts = [
        'customer_due_amount' => SafeDecimalCast::class,
    ];

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

    public function payments()
    {
        return $this->hasMany(CustomerPayment::class, 'customer_id');
    }

    /* ATTRIBUTES */

    /* CALCULATED FIELDS */


    /* SCOPES */
    public function scopeForTeam($query, $teamId)
    {
        $teamIds = TeamModel::findOrFail($teamId)->getDescendants();

        return $query->whereIn('team_id', $teamIds);
    }

    /* ACTIONS */

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

    /**
     * @deprecated Use CustomerService::fillInvoiceWithCustomerData() instead
     * Maintained for backward compatibility
     */
    public function fillInvoiceForCustomer(Invoice $initialInvoice)
    {
        return CustomerService::fillInvoiceWithCustomerData($this, $initialInvoice);
    }

    /* INTEGRITY */
    public static function columnsIntegrityCalculations()
    {
        return [
            'customer_due_amount' => DB::raw('calculate_customer_due(fin_customers.id)'),
        ];
    }

    /* ELEMENTS */
}
