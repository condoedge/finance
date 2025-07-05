<?php

namespace Condoedge\Finance\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Condoedge\Finance\Models\Product createProduct(\Condoedge\Finance\Models\Dto\Products\CreateProductDto $dto)
 * @method static \Condoedge\Finance\Models\Product updateProduct(\Condoedge\Finance\Models\Dto\Products\UpdateProductDto $dto)
 * @method static \Condoedge\Finance\Models\Product createProductFromInvoiceDetail(int $invoiceDetailId)
 * @method static bool deleteProduct(int $productId)
 * @method static \Condoedge\Finance\Models\Product|null findProduct(int $productId)
 * @method static \Illuminate\Database\Eloquent\Collection getAllProducts()
 * @method static \Illuminate\Database\Eloquent\Collection getProductTemplates()
 * @method static \Condoedge\Finance\Models\Product createOrUpdateProduct(\Condoedge\Finance\Models\Dto\Products\CreateProductDto $dto)
 * @method static \Condoedge\Finance\Models\InvoiceDetail copyProductToInvoice(int $productId, int $invoiceId)
 * 
 * @see \Condoedge\Finance\Services\Product\ProductService
 */
class ProductService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Condoedge\Finance\Services\Product\ProductServiceInterface::class;
    }
}
