<?php

namespace Tests\Unit;

use App\Helpers\MathHelper;
use Condoedge\Finance\Database\Factories\AccountFactory;
use Condoedge\Finance\Database\Factories\CustomerFactory;
use Condoedge\Finance\Database\Factories\TaxFactory;
use Condoedge\Finance\Facades\CustomerModel;
use Condoedge\Finance\Facades\InvoiceTypeEnum;
use Condoedge\Finance\Facades\PaymentTypeEnum;
use Condoedge\Finance\Facades\TaxModel;
use Condoedge\Finance\Models\Dto\Invoices\CreateInvoiceDto;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\InvoiceDetailTax;
use Tests\TestCase;

class JsonCasesTest extends TestCase
{
    protected $setupValues = [];

    public function test_cases()
    {
        $jsonFilenames = $this->getTestJsonFilenames();
    
        foreach ($jsonFilenames as $key => $filename) {
            $jsonContent = file_get_contents($filename);
            $jsonData = json_decode($jsonContent, true);

            fwrite(STDOUT, "\n---------------------------------------------------------------------------------
        Testing case " . ($key + 1) . ': ' . $jsonData['testCase'] . "
---------------------------------------------------------------------------------\n\n");
            
            $this->createFromSetup($jsonData['setup']);

            $this->checkInitialState($jsonData['initialState']);

            $this->setupValues['transaction'] = $this->createTransaction($jsonData['transaction']);

            $this->checkExpectedResult($jsonData['expectedResult']);

            fwrite(STDOUT, "✓ {$jsonData['testCase']}.\n");
        }
    }

    protected function checkInitialState($initialState)
    {
        $this->assertEqualsDecimals($initialState['openingBalance'], $this->setupValues['customer']->customer_due_amount);
    }

    protected function getTestJsonFilenames()
    {
        return glob(__DIR__ . '/../**/*.test.json');
    }

    protected function checkExpectedResult($expectedResult)
    {
        $customer = $this->setupValues['customer']->refresh();

        $this->assertEqualsDecimals($expectedResult['newCustomerBalance'], $customer->customer_due_amount);
        
        $documents = $expectedResult['documentStatus'];

        foreach ($documents as $reference => $totalAmount) {
            $prefix = preg_replace('/[^a-zA-Z]/', '', $reference);
            $number = preg_replace('/[^0-9]/', '', $reference);

            $invoices = Invoice::where('customer_id', $this->setupValues['customer']->id)
                ->where('invoice_number', $number)
                ->where('invoice_type_id', collect(InvoiceTypeEnum::cases())->first(function ($case) use ($prefix) {
                    return $case->prefix() === $prefix;
                })->value)
                ->get();

            $this->assertCount(1, $invoices);

            $this->assertEqualsDecimals($totalAmount, $invoices->first()->invoice_total_amount);
        }

        $taxesDetails = $expectedResult['taxCalculation'];

        foreach ($taxesDetails as $taxName => $taxDetails) {
            $detailsRecords = InvoiceDetailTax::getAllForInvoice($this->setupValues['transaction']->id, $taxName);

            $this->assertCount(count($taxDetails), $detailsRecords);

            foreach ($taxDetails as $index => $detail) {
                $dbDetail = $detailsRecords->firstWhere(function ($item) use ($detail, $taxName) {
                    return safeDecimal($item->tax_rate)->equals(safeDecimal($detail['rate'])->divide(100)) && safeDecimal($item->tax_amount)->equals(safeDecimal($detail['amount']));
                });

                $this->assertNotNull($dbDetail, "Tax detail not found for {$taxName} with rate {$detail['rate']} and amount {$detail['amount']}");

                $detailsRecords->forget($dbDetail->tax_name);
            }
        }
    }

    protected function createTransaction($transaction)
    {
        $type = $transaction['type'];

        $methodName = 'create' . $type;

        return $this->$methodName($transaction);
    }

    protected function createInvoice($transaction) 
    {
        return Invoice::createInvoiceFromDto(new CreateInvoiceDto([
            'is_draft' => false,
            'customer_id' => $this->setupValues['customer']->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('INVOICE')->value,
            'payment_type_id' => PaymentTypeEnum::getEnumCase('CASH')->value,
            'invoice_date' => $transaction['date'],
            'invoice_due_date' => $transaction['dueDate'],
            'invoiceDetails' => collect($transaction['lineItems'])->map(function ($line) {
                return [
                    'name' => $line['description'],
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unitPrice'],
                    'revenue_account_id' => AccountFactory::new()->create()->id,
                    'taxesIds' => collect($line['taxes'])->map(function ($tax) {
                        return $this->setupValues['taxes'][$tax]->id;
                    })->toArray(),
                ];
            })->toArray(),
        ]));
    }

    protected function createFromSetup($setup)
    {
        $customer = $this->createCustomerTest($setup['customer']);

        $taxDetails = $setup['taxConfiguration'];

        $taxes = [];
        foreach ($taxDetails as $tax) {
            $taxes[] = $this->createTaxTest($tax['name'], $tax['rate']);
        }

        $this->setupValues = [
            'customer' => $customer,
            'taxes' => collect($taxes)->keyBy('name')->all(),
        ];
    }

    protected function createTaxTest($name, $rate)
    {
        if ($tax = TaxModel::where('name', $name)->first()) {
            $tax->rate = $rate / 100;
            $tax->save();

            return $tax;
        }

        return TaxFactory::new()->create([
            'name' => $name,
            'rate' => $rate / 100,
        ]);
    }

    protected function createCustomerTest($name)
    {
        if ($customer = CustomerModel::where('name', $name)->first()) {
            return $customer;
        }

        return CustomerFactory::new()->create([
            'name' => $name,
        ]);
    }
}