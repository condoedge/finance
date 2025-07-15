<?php

namespace Condoedge\Finance\Services\Product;

use Condoedge\Finance\Facades\InvoiceDetailService;
use Condoedge\Finance\Models\Dto\Invoices\CreateOrUpdateInvoiceDetail;
use Condoedge\Finance\Models\Dto\Products\CreateProductDto;
use Condoedge\Finance\Models\Dto\Products\UpdateProductDto;
use Condoedge\Finance\Models\InvoiceDetail;
use Condoedge\Finance\Models\Product;
use Condoedge\Finance\Models\ProductTypeEnum;
use Illuminate\Support\Facades\DB;

class ProductService implements ProductServiceInterface
{
    /**
     * Create a new product
     */
    public function createProduct(CreateProductDto $dto): Product
    {
        return DB::transaction(function () use ($dto) {
            $product = new Product();

            // Manual field assignment following the pattern
            $product->productable_type = $dto->productable_type;
            $product->productable_id = $dto->productable_id;
            $product->product_type = $dto->product_type;
            $product->product_cost_abs = $dto->product_cost_abs;
            $product->product_name = $dto->product_name;
            $product->product_description = $dto->product_description;
            $product->product_template_id = $dto->product_template_id;
            $product->default_revenue_account_id = $dto->default_revenue_account_id;
            $product->taxes_ids = integerArray($dto->taxes_ids);
            $product->team_id = $dto->team_id ?? currentTeamId();
            $product->product_cost = $product->product_type->getSignedValue($product);
            $product->save();

            return $product->refresh();
        });
    }

    /**
     * Update an existing product
     */
    public function updateProduct(UpdateProductDto $dto): Product
    {
        return DB::transaction(function () use ($dto) {
            $product = Product::findOrFail($dto->id);

            $product->productable_type = $dto->productable_type ?? $product->productable_type;
            $product->productable_id = $dto->productable_id ?? $product->productable_id;
            $product->product_type = $dto->product_type ?? $product->product_type;
            $product->product_cost_abs = $dto->product_cost_abs ?? $product->product_cost_abs;
            $product->product_name = $dto->product_name ?? $product->product_name;
            $product->product_description = $dto->product_description ?? $product->product_description;
            $product->product_template_id = $dto->product_template_id ?? $product->product_template_id;
            $product->default_revenue_account_id = $dto->default_revenue_account_id ?? $product->default_revenue_account_id;
            $product->taxes_ids = integerArray($dto->taxes_ids ?? $product->taxes_ids);
            $product->product_cost = $product->product_type->getSignedValue($product);
            $product->save();

            return $product->refresh();
        });
    }

    /**
     * Create a product from an invoice detail
     */
    public function createProductFromInvoiceDetail(int $invoiceDetailId): Product
    {
        return DB::transaction(function () use ($invoiceDetailId) {
            $invoiceDetail = InvoiceDetail::with(['invoice', 'invoiceTaxes.tax'])->findOrFail($invoiceDetailId);

            // Check if product already exists for this detail
            if ($invoiceDetail->product_id) {
                throw new \Exception(__('finance-product-already-exists-for-invoice-detail'));
            }

            // Get tax IDs from invoice detail taxes
            $taxesIds = $invoiceDetail->invoiceTaxes->pluck('tax_id')->toArray();

            // Create product from invoice detail data
            $dto = new CreateProductDto([
                'productable_type' => null,
                'productable_id' => null,
                'product_type' => ProductTypeEnum::PRODUCT_COST->value,
                'product_cost_abs' => $invoiceDetail->unit_price->toFloat(),
                'product_name' => $invoiceDetail->name,
                'product_description' => $invoiceDetail->description,
                'product_template_id' => null,
                'default_revenue_account_id' => $invoiceDetail->revenue_account_id,
                'taxes_ids' => $taxesIds,
                'team_id' => $invoiceDetail->invoice->team_id,
            ]);

            $product = $this->createProduct($dto);

            // Update invoice detail to reference the new product
            $invoiceDetail->product_id = $product->id;
            $invoiceDetail->save();

            return $product;
        });
    }

    public function normalizeToInvoiceDetail($productId, $invoice = null)
    {
        $product = Product::findOrFail($productId);

        return array_filter([
            'invoiceable_type' => 'product',
            'invoiceable_id' => $product->id,
            'name' => $product->product_name,
            'description' => $product->product_description,
            'unit_price' => $product->getAmount()->toFloat(),
            'quantity' => 1,
            'revenue_account_id' => $product->default_revenue_account_id,
            'taxesIds' => $product->taxes_ids ?: [],
            'invoice_id' => $invoice ? $invoice->id : null,
            'product_id' => $product->id,
        ]);
    }

    /**
     * Delete a product
     */
    public function deleteProduct(int $productId): bool
    {
        return DB::transaction(function () use ($productId) {
            $product = Product::findOrFail($productId);

            // Check if product has children (is a template)
            if ($product->children()->count() > 0) {
                abort(403, __('error.cannot-delete-a-template-that-has-products-associated'));
            }

            // Check if product is being used in invoice details
            $invoiceDetailsCount = InvoiceDetail::where('product_id', $productId)->count();
            if ($invoiceDetailsCount > 0) {
                throw new \Exception(__('finance-cannot-delete-product-in-use'));
            }

            return $product->forceDelete();
        });
    }

    /**
     * Find a product by ID
     */
    public function findProduct(int $productId): ?Product
    {
        return Product::find($productId);
    }

    /**
     * Get all products for current team
     */
    public function getAllProducts()
    {
        $teamId = function_exists('currentTeamId') ? currentTeamId() : 1;

        return Product::forTeam($teamId)
            ->with(['defaultRevenueAccount', 'productTemplate'])
            ->orderBy('product_name')
            ->get();
    }

    /**
     * Get all product templates for current team
     */
    public function getProductTemplates()
    {
        $teamId = function_exists('currentTeamId') ? currentTeamId() : 1;

        return Product::forTeam($teamId)
            ->isTemplate()
            ->with('defaultRevenueAccount')
            ->orderBy('product_name')
            ->get();
    }

    /**
     * Create or update a product based on productable
     */
    public function createOrUpdateProduct(CreateProductDto $dto): Product
    {
        // Check if product exists for this productable
        if ($dto->productable_type && $dto->productable_id) {
            $existingProduct = Product::where('productable_type', $dto->productable_type)
                ->where('productable_id', $dto->productable_id)
                ->where('product_type', $dto->product_type)
                // ->forTeam($dto->team_id ?? currentTeamId())
                ->first();

            if ($existingProduct) {
                // Update existing product
                $updateDto = new UpdateProductDto($dto->toArray() + ['id' => $existingProduct->id]);
                return $this->updateProduct($updateDto);
            }
        }

        // Create new product
        return $this->createProduct($dto);
    }

    /**
     * Copy a product to a new invoice
     */
    public function copyProductToInvoice(int $productId, int $invoiceId): InvoiceDetail
    {
        $invoice = \Condoedge\Finance\Models\Invoice::find($invoiceId);

        return InvoiceDetailService::createInvoiceDetail(new CreateOrUpdateInvoiceDetail(
            $this->normalizeToInvoiceDetail($productId, $invoice)
        ));
    }
}
