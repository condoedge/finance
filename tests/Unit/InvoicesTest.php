<?php

namespace Tests\Unit;

use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Database\Factories\AccountFactory;
use Condoedge\Finance\Database\Factories\CustomerFactory;
use Condoedge\Finance\Database\Factories\InvoiceFactory;
use Condoedge\Finance\Database\Factories\TaxFactory;
use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Facades\InvoiceTypeEnum;
use Condoedge\Finance\Facades\PaymentMethodEnum;
use Condoedge\Finance\Models\Dto\Invoices\CreateInvoiceDto;
use Condoedge\Finance\Models\Dto\Invoices\UpdateInvoiceDto;
use Condoedge\Finance\Models\InvoiceStatusEnum;
use Exception;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Kompo\Auth\Database\Factories\UserFactory;
use Tests\TestCase;

class InvoicesTest extends TestCase
{
    use WithFaker;

    public function testCreateInvoice()
    {
        /** @var \Kompo\Auth\Models\User $user */
        $user = UserFactory::new()->create()->first();

        if (!$user) throw new Exception('Unknown error creating user');

        $this->actingAs($user);

        $customer = CustomerFactory::new()->create();

        $quantity = $this->faker->numberBetween(1, 10);
        $unitPrice = $this->faker->randomFloat(2, 1, 1000);

        $taxes = [
            TaxFactory::new()->create([
                'rate' => $this->faker->randomFloat(2, 0, 1),
            ]),
        ];

        $invoiceDate = now();

        $invoice = InvoiceService::createInvoice(new CreateInvoiceDto([
            'customer_id' => $customer->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('INVOICE')->value,
            'payment_method_id' => PaymentMethodEnum::getEnumCase('CASH')->value,
            'invoice_date' => $invoiceDate,
            'invoice_due_date' => $invoiceDate->copy()->addDays(30),
            'is_draft' => true,
            'invoiceDetails' => [
                [
                    'name' => 'Test Item',
                    'description' => 'Test Description',
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'revenue_account_id' => AccountFactory::new()->create()->id,
                    'taxesIds' => collect($taxes)->pluck('id')->all(),
                ],
            ],
        ]));

        $expectedAmountBeforeTaxes = safeDecimal($quantity)->multiply($unitPrice);
        $expectedTotalAmount = safeDecimal($expectedAmountBeforeTaxes)->multiply(collect($taxes)->sumDecimals('rate')->add(1));

        $this->assertDatabaseHas('fin_invoices', [
            'customer_id' => $customer->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('INVOICE')->value,
            'payment_method_id' => PaymentMethodEnum::getEnumCase('CASH')->value,
            'invoice_date' => db_datetime_format($invoiceDate),
            'invoice_due_date' => db_datetime_format($invoiceDate->copy()->addDays(30)),
            'is_draft' => 1,
            'invoice_amount_before_taxes' => $expectedAmountBeforeTaxes,
            'invoice_status_id' => InvoiceStatusEnum::DRAFT->value,
            'invoice_total_amount' => $expectedTotalAmount,
            'invoice_due_amount' => $expectedTotalAmount,
            'invoice_tax_amount' => safeDecimal($expectedTotalAmount)->subtract($expectedAmountBeforeTaxes),
            'invoice_number' => 1,
            'invoice_reference' => InvoiceTypeEnum::getEnumClass()::INVOICE->prefix() . '-' . str_pad('1', 8, '0', STR_PAD_LEFT),
        ]);

        $previousName = $customer->getOriginal('name');
        $customer->name = 'Updated Customer Name';
        $customer->save();

        $this->assertDatabaseHas('fin_historical_customers', [
            'customer_id' => $customer->id,
            'name' => $previousName,
        ]);

        $this->assertDatabaseHas('addresses', [
            'addressable_id' => $invoice->id,
            'addressable_type' => 'invoice',
        ]);

        $this->assertDatabaseHas('fin_invoice_details', [
            'invoice_id' => $invoice->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'extended_price' => safeDecimal($quantity)->multiply($unitPrice),
            'tax_amount' => safeDecimal($expectedTotalAmount)->subtract($expectedAmountBeforeTaxes),
            'total_amount' => $expectedTotalAmount,
        ]);

        $this->assertDatabaseHas('fin_invoice_detail_taxes', [
            'invoice_detail_id' => $invoice->invoiceDetails[0]->id,
            'tax_id' => $taxes[0]->id,
            'tax_amount' => safeDecimal($expectedTotalAmount)->subtract($expectedAmountBeforeTaxes),
        ]);

        $this->assertDatabaseHas('fin_customers', [
            'id' => $customer->id,
            'customer_due_amount' => 0,
        ]);

        $invoice->markApproved();

        $this->assertDatabaseHas('fin_customers', [
            'id' => $customer->id,
            'customer_due_amount' => $expectedTotalAmount,
        ]);
    }

    public function testUpdateInvoice()
    {
        /** @var \Kompo\Auth\Models\User $user */
        $user = UserFactory::new()->create()->first();

        if (!$user) throw new Exception('Unknown error creating user');

        $this->actingAs($user);

        $quantity = $this->faker->numberBetween(1, 10);
        $unitPrice = $this->faker->randomFloat(2, 1, 1000);

        $taxes = [
            TaxFactory::new()->create([
                'rate' => $this->faker->randomFloat(2, 0, 1),
            ]),
        ];

        $invoice = InvoiceFactory::new()->create([
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('INVOICE')->value,
        ]);

        InvoiceService::updateInvoice(new UpdateInvoiceDto([
            'id' => $invoice->id,
            'payment_method_id' => $invoice->payment_method_id,
            'invoice_date' => $invoice->invoice_date,
            'invoice_due_date' => $invoice->invoice_due_date,
            'is_draft' => true,
            'invoiceDetails' => [
                [
                    'name' => 'Test Item',
                    'description' => 'Test Description',
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'revenue_account_id' => AccountFactory::new()->create()->id,
                    'taxesIds' => collect($taxes)->pluck('id')->all(),
                ],
            ],
        ]));

        $expectedAmountBeforeTaxes = safeDecimal($quantity)->multiply($unitPrice);
        $expectedTotalAmount = safeDecimal($expectedAmountBeforeTaxes)->multiply(collect($taxes)->sumDecimals('rate')->add(1));

        $this->assertDatabaseHas('fin_invoices', [
            'id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'invoice_type_id' => $invoice->invoice_type_id,
            'payment_method_id' => $invoice->payment_method_id,
            'invoice_date' => db_datetime_format($invoice->invoice_date),
            'invoice_due_date' => db_datetime_format($invoice->invoice_due_date),
            'is_draft' => 1,
            'invoice_amount_before_taxes' => $expectedAmountBeforeTaxes,
            'invoice_status_id' => InvoiceStatusEnum::DRAFT->value,
            'invoice_total_amount' => $expectedTotalAmount,
            'invoice_due_amount' => $expectedTotalAmount,
            'invoice_tax_amount' => safeDecimal($expectedTotalAmount)->subtract($expectedAmountBeforeTaxes),
            'invoice_number' => $invoice->invoice_number,
            'invoice_reference' => InvoiceTypeEnum::getEnumClass()::INVOICE->prefix() . '-' . str_pad($invoice->invoice_number, 8, '0', STR_PAD_LEFT),
        ]);
    }
}