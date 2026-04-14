<?php

namespace Condoedge\Finance\Services\Product;

use Condoedge\Finance\Models\Dto\Products\CreateProductDto;
use Condoedge\Finance\Models\Dto\Products\CreateRebateDto;
use Condoedge\Finance\Models\Dto\Products\UpdateProductDto;
use Condoedge\Finance\Models\InvoiceDetail;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\Product;
use Condoedge\Finance\Models\Rebate;

interface ProductServiceInterface
{
    /**
     * Create a new product
     *
     * @param CreateProductDto $dto
     *
     * @return Product
     */
    public function createProduct(CreateProductDto $dto): Product;

    /**
     * Update an existing product
     *
     * @param UpdateProductDto $dto
     *
     * @return Product
     */
    public function updateProduct(UpdateProductDto $dto): Product;

    /**
     * Create a product from an invoice detail
     *
     * @param int $invoiceDetailId
     *
     * @return Product
     */
    public function createProductFromInvoiceDetail(int $invoiceDetailId): Product;

    /**
     * Delete a product
     *
     * @param int $productId
     *
     * @return bool
     */
    public function deleteProduct(int $productId): bool;

    /**
     * Find a product by ID
     *
     * @param int $productId
     *
     * @return Product|null
     */
    public function findProduct(int $productId): ?Product;

    /**
     * Get all products for current team
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllProducts();

    /**
     * Get all product templates for current team
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getProductTemplates();

    /**
     * Create or update a product based on productable
     *
     * @param CreateProductDto $dto
     *
     * @return Product
     */
    public function createOrUpdateProduct(CreateProductDto $dto): Product;

    /**
     * Copy a product to a new invoice
     *
     * @param int $productId
     * @param int $invoiceId
     *
     * @return InvoiceDetail
     */
    public function copyProductToInvoice(int $productId, int $invoiceId): InvoiceDetail;

    public function createRebate(CreateRebateDto $dto): Rebate;

    /**
     * Normalize a product into an invoice detail array
     *
     * @param int $productId
     * @param Invoice|null $invoice
     *
     * @return array
     */
    public function normalizeToInvoiceDetail(int $productId, ?Invoice $invoice = null): array;

    /**
     * Normalize a product and its rebates into invoice detail arrays
     *
     * @param int $productId
     * @param Invoice|null $invoice
     *
     * @return \Illuminate\Support\Collection
     */
    public function normalizeInvoiceDetailsIncludingRebates(int $productId, ?Invoice $invoice = null);

    /**
     * Update an existing rebate
     *
     * @param int $rebateId
     * @param CreateRebateDto $dto
     *
     * @return Rebate
     */
    public function updateRebate(int $rebateId, CreateRebateDto $dto): Rebate;

    /**
     * Create or update a rebate
     *
     * @param CreateRebateDto $dto
     * @param int|null $rebateId
     *
     * @return Rebate
     */
    public function upsertRebate(CreateRebateDto $dto, ?int $rebateId = null): Rebate;
}
