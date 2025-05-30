<?php

namespace Condoedge\Finance\Models\Payable;

use Condoedge\Finance\Casts\SafeDecimalCast;
use Condoedge\Finance\Events\VendorCreated;
use Condoedge\Finance\Models\AbstractMainFinanceModel;
use Condoedge\Finance\Models\Dto\Vendors\CreateOrUpdateVendorDto;
use Condoedge\Finance\Models\Dto\Vendors\CreateVendorFromCustomable;
use Condoedge\Utils\Facades\GlobalConfig;
use Illuminate\Support\Facades\DB;
use Condoedge\Utils\Models\ContactInfo\Maps\Address;

/**
 * Class Vendor
 * 
 * @package Condoedge\Finance\Models\Payable
 * 
 * This table handles vendor information for payable transactions.
 * 
 * @property int $id
 * @property string $name
 * @property int $team_id 
 * @property \Condoedge\Finance\Casts\SafeDecimal $vendor_due_amount @CALCULATED BY calculate_vendor_due() - Remaining amount to be paid
 * @property int|null $default_billing_address_id Foreign key to fin_addresses
 * @property int|null $default_payment_type_id Foreign key to fin_payment_types
 * @property int|null $default_tax_group_id Foreign key to fin_taxes_groups
 */
class Vendor extends AbstractMainFinanceModel
{
    use \Condoedge\Utils\Models\Traits\BelongsToTeamTrait;
    use \Condoedge\Utils\Models\ContactInfo\Maps\MorphManyAddresses;

    protected $casts = [
        'vendor_due_amount' => SafeDecimalCast::class,
    ];

    public static function boot()
    {
        static::updated(function ($model) {
            if ($customable = $model->customable()->first()) {
                $customable->updateFromVendor($model);
            }
        });

        parent::boot();
    }
    
    protected $table = 'fin_vendors';

    protected function getCreatedEventClass()
    {
        return VendorCreated::class;
    }
    
    /* RELATIONSHIPS */
    public function bills()
    {
        return $this->hasMany(Bill::class, 'vendor_id');
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
    public static function createOrEditFromDto(CreateOrUpdateVendorDto $dto)
    {
        if (isset($dto->id)) {
            $vendor = self::findorFail($dto->id);
            $vendor->name = $dto->name;
            $vendor->save();

            return $vendor;
        }

        $vendor = new self;
        $vendor->name = $dto->name;
        $vendor->team_id = $dto->team_id ?? currentTeamId();
        $vendor->save();

        Address::createMainForFromRequest($vendor, $dto->address->toArray());

        return $vendor;
    }

    public static function createOrEditFromCustomable(CreateVendorFromCustomable $dto)
    {
        $vendor = $dto->customable->upsertVendorFromThisModel();

        if ($dto->address) {
            Address::createMainForFromRequest($vendor, $dto->address->toArray());
        }

        return $vendor;
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

    public function fillBillForVendor(Bill $initialBill)
    {
        if (!$this->default_billing_address_id) {
            throw new \Illuminate\Database\Eloquent\RelationNotFoundException(__('translate.vendor-address-not-set'));
        }

        $bill = $initialBill;
        $bill->vendor_id = $this->id;
        // $bill->tax_group_id = $this->defaultAddress->tax_group_id ?? GlobalConfig::getOrFail('default_tax_group_id');
        $bill->payment_type_id = $this->default_payment_type_id ?? GlobalConfig::getOrFail('default_payment_type_id');
    }

    // SCOPES

    /* INTEGRITY */
    public static function columnsIntegrityCalculations()
    {
        return [
            'vendor_due_amount' => DB::raw('calculate_vendor_due(fin_vendors.id)'),
        ];
    }

    /* ELEMENTS */
}
