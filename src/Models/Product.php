<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Facades\InvoiceDetailService;
use Condoedge\Finance\Models\ProductTypeEnum;
use Condoedge\Finance\Facades\ProductTypeEnum as ProductTypeEnumFacade;
use Condoedge\Finance\Models\Dto\Invoices\CreateOrUpdateInvoiceDetail;
use Condoedge\Finance\Models\AbstractMainFinanceModel;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\SegmentValue;
use Condoedge\Utils\Models\Model;

class Product extends AbstractMainFinanceModel
{
    use \Kompo\Auth\Models\Teams\BelongsToTeamTrait;

    protected $casts = [
        'product_type' => ProductTypeEnum::class,
        'taxes_ids' =>  'array',
    ];

    protected $table = 'fin_products';

    public function save(array $options = [])
    {
        if ($this->countInTotal() && !$this->product_template_id) {
            $this->product_cost_total = $this->product_type->getValue($this);
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
            fn($q) => $q->product()
                ->orWhere(fn($q) => $q->service())
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
            return $this->template->product_cost_total;
        }

        return $this->product_cost_total;
    }

    public function getCommissionAmount()
    {
        return $this->product_type->isCommission() ? $this->product_cost : 0;
    }

    /* ACTIONS */
    public static function createProduct(?Model $productable, ProductTypeEnum $type, float $amount, $name = '', $templateId = null, $description = '', $accountId = null)
    {
        $cost = new static();
        $cost->productable_type = $productable?->getMorphClass();
        $cost->productable_id = $productable?->getKey();
        $cost->product_type = $type;
        $cost->product_cost = $amount;
        $cost->product_name = $name ?: $type->label();
        $cost->product_description = $description;
        $cost->product_template_id = $templateId;
        $cost->default_revenue_account_id = $accountId ?? GlAccount::getFromLatestSegmentValue(SegmentValue::first()?->id)->id; // ! TODO we must add a real way to get the account here
        $cost->team_id = currentTeamId();
        $cost->save();

        return $cost;
    }

    public static function createCost(?Model $productable, ProductTypeEnum $type, float $amount, $name = '', $templateId = null, $description = '', $accountId = null)
    {
        return static::createProduct($productable, $type, $amount, $name, $templateId, $description, $accountId);
    }

    public static function createOrUpdateProduct(Model $productable, ProductTypeEnum $type, float $amount, $name = '', $accountId = null)
    {
        $cost = static::where('productable_type', $productable->getMorphClass())
            ->where('productable_id', $productable->getKey())
            ->where('product_type', $type)->forTeam()->first();

        if (!$cost) {
            $cost = new static();
        }

        $cost->productable_type = $productable->getMorphClass();
        $cost->productable_id = $productable->getKey();
        $cost->product_type = $type;
        $cost->product_cost = $amount;
        $cost->product_name = $name ?: $type->label();
        $cost->default_revenue_account_id = $accountId ?? GlAccount::getFromLatestSegmentValue(SegmentValue::first()?->id)->id; // ! TODO we must add a real way to get the account here
        $cost->team_id = currentTeamId();
        $cost->save();

        return $cost;
    }

    public static function createOrUpdateCost(Model $productable, ProductTypeEnum $type, float $amount, $name = '')
    {
        return static::createOrUpdateProduct($productable, $type, $amount, $name);
    }

    public static function createFromInvoiceDetail(InvoiceDetail $invoiceDetail)
    {
        $product = static::createProduct(
            null,
            ProductTypeEnum::PRODUCT_COST,
            $invoiceDetail->unit_price->toFloat(),
            $invoiceDetail->name,
            null,
            $invoiceDetail->description,
            $invoiceDetail->revenue_account_id
        );

        $invoiceDetail->product_id = $product->id;
        $invoiceDetail->save();
    }

    public function normalizeToInvoiceDetail($invoice = null)
    {
        return [
            'invoiceable_type' => 'product',
            'invoiceable_id' => $this->id,
            'name' => $this->product_name,
            'description' => $this->product_description,
            'unit_price' => $this->getAmount(),
            'quantity' => 1,
            'revenue_account_id' => $this->default_revenue_account_id,
            'taxesIds' => $this->taxes_ids ?: [],
            'invoice_id' => $invoice ? $invoice->id : null,
            'product_id' => $this->id,
        ];
    }

    public function copyToInvoice($invoice)
    {
        return InvoiceDetailService::createInvoiceDetail(new CreateOrUpdateInvoiceDetail(
            $this->normalizeToInvoiceDetail($invoice)
        ));
    }

    public function createProductCopy($productable)
    {
        return static::createProduct($productable, $this->product_type, $this->product_cost, $this->product_name, $this->id, $this->product_description);
    }

    public function createCostCopy($productable)
    {
        return static::createProductCopy($productable);
    }

    public function delete()
    {
        if ($this->children()->count() > 0) {
            return abort(403, __('error.cannot-delete-a-template-that-has-products-associated'));
        }

        return parent::delete();
    }
}
