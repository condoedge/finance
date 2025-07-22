<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Casts\SafeDecimalCast;
use Condoedge\Finance\Facades\ProductService;
use Condoedge\Finance\Facades\TaxService;
use Condoedge\Finance\Models\Dto\Products\CreateProductDto;
use Condoedge\Utils\Models\Model;
use Illuminate\Support\Facades\DB;

/**
 * A class representing a financial product, service, or commission. (rebates as well)
 *
 * @property int $id
 * @property string $productable_type If it's associated to a representative model of what this product is for
 * @property int $productable_id The id of the associated model
 * @property int|null $product_template_id The id of the product template if this is a template-based product
 * @property string $product_name The name of the product
 * @property string|null $product_description A description of the product
 * @property SafeDecimal $product_cost_abs The base cost of the product (always positive)
 * @property SafeDecimal|null $product_cost Product cost adjusted for sign sensitivity based on product type
 * @property SafeDecimal|null $product_cost_total The total cost of the product including taxes (if applicable)
 * @property int|null $team_id The team this product belongs to
 * @property int $default_revenue_account_id The default GL account for revenue associated with this product
 * @property array|null $taxes_ids An array of tax IDs that apply to this product
 * @property ProductTypeEnum $product_type
 */
class Product extends AbstractMainFinanceModel
{
    use \Kompo\Auth\Models\Teams\BelongsToTeamTrait;

    protected $casts = [
        'product_type' => ProductTypeEnum::class,
        'taxes_ids' =>  'array',
        'product_cost_abs' => SafeDecimalCast::class,
        'product_cost' => SafeDecimalCast::class,
        'product_cost_total' => SafeDecimalCast::class,
    ];

    protected $table = 'fin_products';

    public function save(array $options = [])
    {
        // We're also doing it in the product service, this is just to ensure
        if ($this->countInTotal() && !$this->product_template_id) {
            $this->product_cost = $this->product_type->getSignedValue($this);
        }

        if ($this->isDirty('taxes_ids')) {
            $this->taxes_ids = integerArray($this->taxes_ids);
        }

        parent::save($options);
    }

    /* RELATIONS */
    public function productable()
    {
        return $this->morphTo();
    }

    public function template()
    {
        return $this->belongsTo(Product::class, 'product_template_id');
    }

    public function children()
    {
        return $this->hasMany(Product::class, 'product_template_id');
    }

    public function defaultRevenueAccount()
    {
        return $this->belongsTo(GlAccount::class, 'default_revenue_account_id');
    }

    /* SCOPES */
    public function scopeTeamCommission($query)
    {
        return $query->where('product_type', ProductTypeEnum::TEAM_LEVEL_COMMISSION);
    }

    public function scopeProduct($query)
    {
        return $query->where('product_type', ProductTypeEnum::PRODUCT_COST);
    }

    public function scopeService($query)
    {
        return $query->where('product_type', ProductTypeEnum::SERVICE_COST);
    }

    public function scopeMain($query)
    {
        return $query->where(
            fn ($q) => $q->product()
                ->orWhere(fn ($q) => $q->service())
        );
    }

    public function scopeCountInTotal($query)
    {
        return $query->whereIn('product_type', ProductTypeEnum::getTypesCountInTotal());
    }

    public function scopeCommission($query)
    {
        return $query->where('product_type', ProductTypeEnum::getTypesCommissions());
    }

    public function scopeIsTemplate($query)
    {
        return $query->whereNull('product_template_id');
    }

    public function scopeIsNotTemplate($query)
    {
        return $query->whereNotNull('product_template_id');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('product_name', 'like', wildcardSpace($search))
                ->orWhere('product_description', 'like', wildcardSpace($search));
        });
    }

    /* CALCULATED FIELDS */
    public function deletable()
    {
        if ($this->productable) {
            return $this->productable->team_id == currentTeamId() || $this->productable->team->getParentTeams(currentTeam()->team_level)->contains(currentTeam());
        }

        return $this->team_id == currentTeamId();
    }

    public function countInTotal()
    {
        return $this->product_type->countInTotal();
    }

    public function getAmount()
    {
        if ($this->template) {
            return $this->template->product_cost;
        }

        return $this->product_cost;
    }

    public function getCommissionAmount()
    {
        return safeDecimal($this->product_type->isCommission() ? $this->product_cost : 0);
    }

    /* ACTIONS */
    /**
     * @deprecated Use ProductService::createProduct() instead
     * Maintained for backward compatibility
     */
    public static function createProduct(?Model $productable, ProductTypeEnum $type, SafeDecimal|float $amount, $name = '', $templateId = null, $description = '', $accountId = null)
    {
        return ProductService::createProduct(new CreateProductDto([
            'productable_type' => $productable?->getMorphClass(),
            'productable_id' => $productable?->getKey(),
            'product_type' => $type->value,
            'product_cost_abs' => $amount,
            'product_name' => $name ?: $type->label(),
            'product_description' => $description,
            'product_template_id' => $templateId,
            'default_revenue_account_id' => $accountId ?? GlAccount::getFromLatestSegmentValue(SegmentValue::first()?->id)->id, // ! TODO we must add a real way to get the account here
            'team_id' => currentTeamId(),
        ]));
    }

    /**
     * @deprecated Use ProductService::createProduct() instead
     * Maintained for backward compatibility
     */
    public static function createCost(?Model $productable, ProductTypeEnum $type, SafeDecimal|float $amount, $name = '', $templateId = null, $description = '', $accountId = null)
    {
        return static::createProduct($productable, $type, $amount, $name, $templateId, $description, $accountId);
    }

    /**
     * @deprecated Use ProductService::createOrUpdateProduct() instead
     * Maintained for backward compatibility
     */
    public static function createOrUpdateProduct(Model $productable, ProductTypeEnum $type, SafeDecimal|float $amount, $name = '', $accountId = null, $teamId = null)
    {
        return ProductService::createOrUpdateProduct(new CreateProductDto([
            'productable_type' => $productable->getMorphClass(),
            'productable_id' => $productable->getKey(),
            'product_type' => $type->value,
            'product_cost_abs' => $amount,
            'product_name' => $name ?: $type->label(),
            'default_revenue_account_id' => $accountId ?? GlAccount::getFromLatestSegmentValue(SegmentValue::first()?->id)->id, // ! TODO we must add a real way to get the account here
            'team_id' => $teamId ?? currentTeamId(),
            'taxes_ids' => TaxService::getDefaultTaxesIds([
                'team_id' => $teamId ?? currentTeamId(),
            ]),
        ]));
    }

    /**
     * @deprecated Use ProductService::createOrUpdateProduct() instead
     * Maintained for backward compatibility
     */
    public static function createOrUpdateCost(Model $productable, ProductTypeEnum $type, SafeDecimal|float $amount, $name = '', $teamId = null)
    {
        return static::createOrUpdateProduct($productable, $type, $amount, $name, $teamId);
    }

    /**
     * @deprecated Use ProductService::createProductFromInvoiceDetail() instead
     * Maintained for backward compatibility
     */
    public static function createFromInvoiceDetail(InvoiceDetail $invoiceDetail)
    {
        return ProductService::createProductFromInvoiceDetail($invoiceDetail->id);
    }

    public function normalizeToInvoiceDetail($invoice = null)
    {
        return ProductService::normalizeToInvoiceDetail($this->id, $invoice);
    }

    /**
     * @deprecated Use ProductService::copyProductToInvoice() instead
     * Maintained for backward compatibility
     */
    public function copyToInvoice($invoice)
    {
        return ProductService::copyProductToInvoice($this->id, $invoice->id);
    }

    public function createProductCopy($productable = null)
    {
        return ProductService::createProduct(new CreateProductDto([
            'productable_type' => $productable?->getMorphClass() ?? $this->productable_type,
            'productable_id' => $productable?->getKey() ?? $this->productable_id,
            'product_type' => $this->product_type->value,
            'product_cost_abs' => $this->product_cost_abs->toFloat(),
            'product_name' => $this->product_name,
            'product_description' => $this->product_description,
            'product_template_id' => $this->id,
            'default_revenue_account_id' => $this->default_revenue_account_id,
            'team_id' => currentTeamId(),
        ]));
    }

    /**
     * @deprecated Use ProductService::createProduct() with template_id instead
     * Maintained for backward compatibility
     */
    public function createCostCopy($productable)
    {
        return static::createProductCopy($productable);
    }

    /**
     * @deprecated Use ProductService::deleteProduct() instead
     * Maintained for backward compatibility
     */
    public function delete()
    {
        return ProductService::deleteProduct($this->id);
    }

    public static function columnsIntegrityCalculations(): array
    {
        return [
            'product_taxes_amount' => DB::raw('calculate_product_taxes_amount(fin_products.id)'),
        ];
    }
}
