<?php

namespace Tests\Unit;

use Condoedge\Finance\Database\Factories\CustomerFactory;
use Condoedge\Finance\Database\Factories\GlAccountFactory;
use Condoedge\Finance\Database\Factories\ProductFactory;
use Condoedge\Finance\Database\Factories\TaxFactory;
use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Facades\InvoiceTypeEnum;
use Condoedge\Finance\Facades\PaymentMethodEnum;
use Condoedge\Finance\Facades\ProductService;
use Condoedge\Finance\Models\Dto\Invoices\CreateInvoiceDto;
use Condoedge\Finance\Models\Dto\Products\CreateProductDto;
use Condoedge\Finance\Models\Dto\Products\UpdateProductDto;
use Condoedge\Finance\Models\InvoiceDetail;
use Condoedge\Finance\Models\Product;
use Condoedge\Finance\Models\ProductTypeEnum;
use Exception;
use Illuminate\Foundation\Testing\WithFaker;
use Kompo\Auth\Database\Factories\UserFactory;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use WithFaker;

    public function setUp(): void
    {
        parent::setUp();

        /** @var \Kompo\Auth\Models\User $user */
        $user = UserFactory::new()->create()->first();
        if (!$user) {
            throw new Exception('Unknown error creating user');
        }
        $this->actingAs($user);
    }

    public function test_create_product()
    {
        $revenueAccount = GlAccountFactory::new()->create();
        $taxes = TaxFactory::new()->count(2)->create();

        $productName = $this->faker->words(3, true);
        $productDescription = $this->faker->sentence();
        $productCost = $this->faker->randomFloat(2, 10, 1000);

        $product = ProductService::createProduct(new CreateProductDto([
            'product_type' => ProductTypeEnum::SERVICE_COST->value,
            'product_name' => $productName,
            'product_description' => $productDescription,
            'product_cost_abs' => $productCost,
            'default_revenue_account_id' => $revenueAccount->id,
            'taxes_ids' => $taxes->pluck('id')->toArray(),
            'team_id' => currentTeamId(),
        ]));

        $this->assertInstanceOf(Product::class, $product);

        $this->assertDatabaseHas('fin_products', [
            'id' => $product->id,
            'product_type' => ProductTypeEnum::SERVICE_COST->value,
            'product_name' => $productName,
            'product_description' => $productDescription,
            'product_cost_abs' => $productCost,
            'default_revenue_account_id' => $revenueAccount->id,
            'team_id' => currentTeamId(),
        ]);

        // Verify taxes_ids are stored correctly as JSON
        $this->assertEquals($taxes->pluck('id')->toArray(), $product->taxes_ids);
    }

    public function test_update_product()
    {
        $product = ProductFactory::new()->create();
        $newRevenueAccount = GlAccountFactory::new()->create();
        $newTaxes = TaxFactory::new()->count(3)->create();

        $newProductName = 'Updated Product Name';
        $newProductDescription = 'Updated product description';
        $newProductCost = $this->faker->randomFloat(2, 100, 2000);

        $updatedProduct = ProductService::updateProduct(new UpdateProductDto([
            'id' => $product->id,
            'product_type' => ProductTypeEnum::PRODUCT_COST->value,
            'product_name' => $newProductName,
            'product_description' => $newProductDescription,
            'product_cost_abs' => $newProductCost,
            'default_revenue_account_id' => $newRevenueAccount->id,
            'taxes_ids' => $newTaxes->pluck('id')->toArray(),
        ]));

        $this->assertDatabaseHas('fin_products', [
            'id' => $product->id,
            'product_type' => ProductTypeEnum::PRODUCT_COST->value,
            'product_name' => $newProductName,
            'product_description' => $newProductDescription,
            'product_cost_abs' => $newProductCost,
            'default_revenue_account_id' => $newRevenueAccount->id,
        ]);

        $this->assertEquals($newTaxes->pluck('id')->toArray(), $updatedProduct->taxes_ids);
    }

    public function test_create_product_from_invoice_detail()
    {
        $customer = CustomerFactory::new()->create();
        $revenueAccount = GlAccountFactory::new()->create();
        $taxes = TaxFactory::new()->count(2)->create();

        $detailName = 'Web Development Service';
        $detailDescription = 'Custom website development';
        $unitPrice = $this->faker->randomFloat(2, 100, 1000);

        // Create invoice with detail
        $invoice = InvoiceService::createInvoice(new CreateInvoiceDto([
            'customer_id' => $customer->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('INVOICE')->value,
            'payment_method_id' => PaymentMethodEnum::getEnumCase('CASH')->value,
            'invoice_date' => now(),
            'invoice_due_date' => now()->addDays(30),
            'is_draft' => true,
            'team_id' => currentTeamId(),
            'invoiceDetails' => [
                [
                    'name' => $detailName,
                    'description' => $detailDescription,
                    'quantity' => 1,
                    'unit_price' => $unitPrice,
                    'revenue_account_id' => $revenueAccount->id,
                    'taxesIds' => $taxes->pluck('id')->toArray(),
                ],
            ],
        ]));

        $invoiceDetail = $invoice->invoiceDetails->first();

        // Create product from invoice detail
        $product = ProductService::createProductFromInvoiceDetail($invoiceDetail->id);

        $this->assertInstanceOf(Product::class, $product);

        $this->assertDatabaseHas('fin_products', [
            'id' => $product->id,
            'product_name' => $detailName,
            'product_description' => $detailDescription,
            'product_cost_abs' => db_decimal_format($unitPrice),
            'default_revenue_account_id' => $revenueAccount->id,
            'team_id' => currentTeamId(),
        ]);

        // Verify taxes were copied
        $this->assertEquals($taxes->pluck('id')->toArray(), $product->taxes_ids);

        // Verify invoice detail was updated with product_id
        $this->assertDatabaseHas('fin_invoice_details', [
            'id' => $invoiceDetail->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_prevent_duplicate_product_from_invoice_detail()
    {
        $customer = CustomerFactory::new()->create();
        $revenueAccount = GlAccountFactory::new()->create();

        // Create invoice with detail
        $invoice = InvoiceService::createInvoice(new CreateInvoiceDto([
            'customer_id' => $customer->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('INVOICE')->value,
            'payment_method_id' => PaymentMethodEnum::getEnumCase('CASH')->value,
            'invoice_date' => now(),
            'invoice_due_date' => now()->addDays(30),
            'is_draft' => true,
            'invoiceDetails' => [
                [
                    'name' => 'Test Service',
                    'description' => 'Test Description',
                    'quantity' => 1,
                    'unit_price' => 100,
                    'revenue_account_id' => $revenueAccount->id,
                    'taxesIds' => [],
                ],
            ],
        ]));

        $invoiceDetail = $invoice->invoiceDetails->first();

        // Create product from invoice detail
        $product = ProductService::createProductFromInvoiceDetail($invoiceDetail->id);

        // Try to create product again from same invoice detail
        $this->expectException(Exception::class);
        ProductService::createProductFromInvoiceDetail($invoiceDetail->id);
    }

    public function test_create_invoice_detail_from_product()
    {
        $product = ProductFactory::new()->create();
        $customer = CustomerFactory::new()->create();

        // Create invoice with detail
        $invoice = InvoiceService::createInvoice(new CreateInvoiceDto([
            'customer_id' => $customer->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('INVOICE')->value,
            'payment_method_id' => PaymentMethodEnum::getEnumCase('CASH')->value,
            'invoice_date' => now(),
            'invoice_due_date' => now()->addDays(30),
            'is_draft' => true,
            'invoiceDetails' => [],
        ]));

        // Apply product to invoice detail
        ProductService::copyProductToInvoice($product->id, $invoice->id);

        $this->assertDatabaseHas('fin_invoice_details', [
            'product_id' => $product->id,
            'name' => $product->product_name,
            'description' => $product->product_description,
            'unit_price' => $product->product_cost_abs,
            'revenue_account_id' => $product->default_revenue_account_id,
            'quantity' => 1,
        ]);
    }

    public function test_product_with_tax_calculations()
    {
        $customer = CustomerFactory::new()->create();
        $revenueAccount = GlAccountFactory::new()->create();
        $tax1 = TaxFactory::new()->create(['rate' => 0.10]); // 10%
        $tax2 = TaxFactory::new()->create(['rate' => 0.05]); // 5%

        $quantity = 2;
        $unitPrice = 100.00;

        // Create invoice with taxable detail
        $invoice = InvoiceService::createInvoice(new CreateInvoiceDto([
            'customer_id' => $customer->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('INVOICE')->value,
            'payment_method_id' => PaymentMethodEnum::getEnumCase('CASH')->value,
            'invoice_date' => now(),
            'invoice_due_date' => now()->addDays(30),
            'is_draft' => true,
            'invoiceDetails' => [
                [
                    'name' => 'Taxable Service',
                    'description' => 'Service with multiple taxes',
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'revenue_account_id' => $revenueAccount->id,
                    'taxesIds' => [$tax1->id, $tax2->id],
                ],
            ],
        ]));

        $invoiceDetail = $invoice->invoiceDetails->first();

        // Create product from invoice detail
        $product = ProductService::createProductFromInvoiceDetail($invoiceDetail->id);

        // Verify taxes were copied correctly
        $this->assertEqualsDecimals($unitPrice, $product->product_cost_abs);
        $this->assertCount(2, $product->taxes_ids);
        $this->assertContains($tax1->id, $product->taxes_ids);
        $this->assertContains($tax2->id, $product->taxes_ids);

        // Verify total amount calculation
        $expectedTotal = safeDecimal($invoiceDetail->total_amount);
        $this->assertEqualsDecimals($expectedTotal, $product->product_cost_total->multiply($quantity));
    }

    public function test_delete_product_with_invoice_details()
    {
        $product = ProductFactory::new()->create();
        $customer = CustomerFactory::new()->create();

        // Create invoice with detail using the product
        $invoice = InvoiceService::createInvoice(new CreateInvoiceDto([
            'customer_id' => $customer->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('INVOICE')->value,
            'payment_method_id' => PaymentMethodEnum::getEnumCase('CASH')->value,
            'invoice_date' => now(),
            'invoice_due_date' => now()->addDays(30),
            'is_draft' => true,
            'invoiceDetails' => [
                [
                    'product_id' => $product->id,
                    'name' => $product->product_name,
                    'description' => $product->product_description,
                    'quantity' => 1,
                    'unit_price' => $product->product_cost_abs->toFloat(),
                    'revenue_account_id' => $product->default_revenue_account_id,
                    'taxesIds' => $product->taxes_ids ?? [],
                ],
            ],
        ]));

        // Try to delete product that's in use
        $this->expectException(Exception::class);
        ProductService::deleteProduct($product->id);
    }

    public function test_normalize_product_to_invoice_detail()
    {
        $taxes = TaxFactory::new()->count(2)->create();
        $product = ProductFactory::new()->create([
            'taxes_ids' => $taxes->pluck('id')->toArray(),
        ]);

        $normalizedData = $product->normalizeToInvoiceDetail();

        $this->assertArrayHasKey('product_id', $normalizedData);
        $this->assertArrayHasKey('name', $normalizedData);
        $this->assertArrayHasKey('description', $normalizedData);
        $this->assertArrayHasKey('unit_price', $normalizedData);
        $this->assertArrayHasKey('revenue_account_id', $normalizedData);
        $this->assertArrayHasKey('taxesIds', $normalizedData);
        $this->assertArrayHasKey('invoiceable_type', $normalizedData);
        $this->assertArrayHasKey('invoiceable_id', $normalizedData);

        $this->assertEquals($product->id, $normalizedData['product_id']);
        $this->assertEquals($product->product_name, $normalizedData['name']);
        $this->assertEquals($product->product_description, $normalizedData['description']);
        $this->assertEqualsDecimals($product->getAmount(), $normalizedData['unit_price']);
        $this->assertEquals($product->default_revenue_account_id, $normalizedData['revenue_account_id']);
        $this->assertEquals($product->taxes_ids ?: [], $normalizedData['taxesIds']);
        $this->assertEquals('product', $normalizedData['invoiceable_type']);
        $this->assertEquals($product->id, $normalizedData['invoiceable_id']);
    }

    public function test_create_product_without_taxes()
    {
        $revenueAccount = GlAccountFactory::new()->create();

        $product = ProductService::createProduct(new CreateProductDto([
            'product_type' => ProductTypeEnum::SERVICE_COST->value,
            'product_name' => 'Tax Free Service',
            'product_description' => 'Service without taxes',
            'product_cost_abs' => 150.00,
            'default_revenue_account_id' => $revenueAccount->id,
            'taxes_ids' => [],
            'team_id' => $revenueAccount->team_id,
        ]));

        $this->assertInstanceOf(Product::class, $product);
        $this->assertEmpty($product->taxes_ids);
    }

    public function testCopyProductToInvoice()
    {
        $taxes = TaxFactory::new()->count(2)->create();
        $product = ProductFactory::new()->create([
            'taxes_ids' => $taxes->pluck('id')->toArray(),
            'product_cost_abs' => 150.00,
        ]);
        $customer = CustomerFactory::new()->create();

        // Create empty invoice
        $invoice = InvoiceService::createInvoice(new CreateInvoiceDto([
            'customer_id' => $customer->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('INVOICE')->value,
            'payment_method_id' => PaymentMethodEnum::getEnumCase('CASH')->value,
            'invoice_date' => now(),
            'invoice_due_date' => now()->addDays(30),
            'is_draft' => true,
            'invoiceDetails' => [],
        ]));

        // Copy product to invoice
        $invoiceDetail = ProductService::copyProductToInvoice($product->id, $invoice->id);

        $this->assertInstanceOf(InvoiceDetail::class, $invoiceDetail);

        $this->assertDatabaseHas('fin_invoice_details', [
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'name' => $product->product_name,
            'description' => $product->product_description,
            'unit_price' => $product->product_cost_abs,
            'revenue_account_id' => $product->default_revenue_account_id,
            'quantity' => 1,
        ]);

        // Verify taxes were copied
        $invoiceDetailTaxes = $invoiceDetail->invoiceTaxes->pluck('tax_id')->toArray();
        $this->assertEquals($taxes->pluck('id')->toArray(), $invoiceDetailTaxes);
    }

    public function test_create_product_with_template()
    {
        $templateProduct = ProductFactory::new()->create([
            'product_name' => 'Template Product',
            'product_description' => 'This is a template product',
            'product_cost_abs' => 200.00,
            'default_revenue_account_id' => GlAccountFactory::new()->create()->id,
            'taxes_ids' => [],
        ]);

        $newProduct = $templateProduct->createProductCopy(null);

        $this->assertInstanceOf(Product::class, $newProduct);
        $this->assertEquals('Template Product', $newProduct->product_name);
        $this->assertEquals('This is a template product', $newProduct->product_description);
        $this->assertEqualsDecimals(200.00, $newProduct->product_cost_abs);
        $this->assertEquals($templateProduct->default_revenue_account_id, $newProduct->default_revenue_account_id);
        $this->assertEmpty($newProduct->taxes_ids);
        $this->assertNotEquals($templateProduct->id, $newProduct->id);

        // The unit_cost is based in the template so if it changes in the template it should also do it in the replication
        $templateProduct->product_cost_abs = 250.00;
        $templateProduct->save();

        $this->assertEqualsDecimals(250.00, $newProduct->getAmount());
    }
}
