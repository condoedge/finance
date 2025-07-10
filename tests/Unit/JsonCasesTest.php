<?php

namespace Tests\Unit;

use Condoedge\Finance\Database\Factories\CustomerFactory;
use Condoedge\Finance\Database\Factories\GlAccountFactory;
use Condoedge\Finance\Database\Factories\PaymentTermFactory;
use Condoedge\Finance\Database\Factories\TaxFactory;
use Condoedge\Finance\Facades\CustomerModel;
use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Facades\InvoiceTypeEnum;
use Condoedge\Finance\Facades\PaymentMethodEnum;
use Condoedge\Finance\Facades\PaymentService;
use Condoedge\Finance\Facades\TaxModel;
use Condoedge\Finance\Models\Dto\Invoices\CreateInvoiceDto;
use Condoedge\Finance\Models\Dto\Payments\CreateAppliesForMultipleInvoiceDto;
use Condoedge\Finance\Models\Dto\Payments\CreateCustomerPaymentDto;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\InvoiceDetailTax;
use Condoedge\Finance\Models\MorphablesEnum;
use Exception;
use Kompo\Auth\Database\Factories\UserFactory;
use Tests\TestCase;

class JsonCasesTest extends TestCase
{
    protected $setupValues = [];

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

    public function test_cases()
    {
        $jsonFilenames = $this->getTestJsonFilenames();

        $groupedFilenames = collect($jsonFilenames)
            ->groupBy(function ($filename) {
                return preg_replace('/.*\.test-?([^\/]*)\..*/', '$1', $filename) ?: 0;
            })
            ->map(function ($group) {
                // Ordenar los archivos dentro de cada grupo
                return $group->sortBy(function ($filename) {
                    // Extraer número de caso del nombre del archivo (por ejemplo, case-1, case-2)
                    if (preg_match('/case-(\d+)/', $filename, $matches)) {
                        return (int)$matches[1];
                    }
                    return $filename; // Fallback a ordenar por nombre si no hay número
                });
            })
            ->sortBy(function ($group, $key) {
                return (int) $key;
            });

        foreach ($groupedFilenames as $group => $jsonFilenames) {
            fwrite(STDOUT, "\n  -------------------------------------------
               TESTING GROUP " . $group . "
  -------------------------------------------\n");
            foreach ($jsonFilenames as $key => $filename) {
                $jsonContent = file_get_contents($filename);
                $jsonData = json_decode($jsonContent, true);

                $this->createFromSetup($jsonData['setup']);

                $this->checkInitialState($jsonData['initialState']);

                $this->setupValues['transaction'] = $this->createTransaction($jsonData['transaction']);

                try {
                    $this->checkExpectedResult($jsonData['expectedResult']);
                } catch (\Exception $e) {
                    fwrite(STDERR, "  ✗ {$jsonData['testCase']} - Error: {$e->getMessage()}\n");

                    throw $e; // Re-throw the exception to fail the test
                }


                fwrite(STDOUT, "  ✓ {$jsonData['testCase']}.\n");
            }

        }

    }

    protected function checkInitialState($initialState)
    {
        $this->assertEqualsDecimals($initialState['openingBalance'], $this->setupValues['customer']->customer_due_amount);
    }

    protected function getTestJsonFilenames()
    {
        return glob(__DIR__ . '/../**/*.test*.json');
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
                ->byReferenceDetails($prefix, $number)
                ->get();

            $this->assertCount(1, $invoices);

            $this->assertEqualsDecimals($totalAmount, $invoices->first()->invoice_due_amount);
        }

        $taxesDetails = $expectedResult['taxCalculation'] ?? [];

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

    protected function createCreditNote($transaction)
    {
        $credit = InvoiceService::createInvoice(new CreateInvoiceDto([
            'is_draft' => false,
            'customer_id' => $this->setupValues['customer']->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('CREDIT')->value,
            'payment_method_id' => PaymentMethodEnum::getEnumCase('CASH')->value,
            'payment_term_id' => PaymentTermFactory::new()->create()->id,
            'invoice_date' => $transaction['date'],
            'invoiceDetails' => collect($transaction['lineItems'])->map(function ($line) {
                return [
                    'name' => $line['description'],
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unitPrice'],
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
                    'taxesIds' => collect($line['taxes'])->map(function ($tax) {
                        return $this->setupValues['taxes'][$tax]->id;
                    })->toArray(),
                ];
            })->toArray(),
        ]));

        $credit->markApproved();

        if (count($transaction['applications'] ?? []) > 0) {
            PaymentService::applyPaymentToInvoices(new CreateAppliesForMultipleInvoiceDto([
                'apply_date' => $transaction['date'],
                'applicable' => $credit,
                'applicable_type' => MorphablesEnum::CREDIT->value,
                'amounts_to_apply' => collect($transaction['applications'])->map(function ($invoice) {
                    $prefix = preg_replace('/[^a-zA-Z]/', '', $invoice['document']);
                    $number = preg_replace('/[^0-9]/', '', $invoice['document']);

                    return [
                        'id' => Invoice::byReferenceDetails($prefix, $number)->first()->id,
                        'amount_applied' => $invoice['amount'],
                    ];
                })->toArray(),
            ]));
        }

        return $credit;
    }

    protected function createCreditApply($transaction)
    {
        $credit = Invoice::byReferenceDetails(
            preg_replace('/[^a-zA-Z]/', '', $transaction['reference']),
            preg_replace('/[^0-9]/', '', $transaction['reference'])
        )->first();

        if (!$credit) {
            throw new \Exception("Credit note not found for document: {$transaction['reference']}");
        }

        if (!collect($transaction['applications'] ?? [])->isEmpty()) {
            PaymentService::applyPaymentToInvoices(new CreateAppliesForMultipleInvoiceDto([
                'apply_date' => $transaction['date'],
                'applicable' => $credit,
                'applicable_type' => MorphablesEnum::CREDIT->value,
                'amounts_to_apply' => collect($transaction['applications'])->map(function ($invoice) {
                    $prefix = preg_replace('/[^a-zA-Z]/', '', $invoice['document']);
                    $number = preg_replace('/[^0-9]/', '', $invoice['document']);

                    return [
                        'id' => Invoice::byReferenceDetails($prefix, $number)->first()->id,
                        'amount_applied' => $invoice['amount'],
                    ];
                })->toArray(),
            ]));
        }

        return $credit;
    }

    protected function createPayment($transaction)
    {
        $payment = PaymentService::createPayment(new CreateCustomerPaymentDto([
            'customer_id' => $this->setupValues['customer']->id,
            'payment_date' => $transaction['date'],
            'amount' => $transaction['amount'],
        ]));

        if (collect($transaction['applications'] ?? null)->isEmpty()) {
            return $payment;
        }

        PaymentService::applyPaymentToInvoices(new CreateAppliesForMultipleInvoiceDto([
            'apply_date' => $transaction['date'],
            'applicable' => $payment,
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amounts_to_apply' => collect($transaction['applications'] ?? null)->map(function ($invoice) {
                $prefix = preg_replace('/[^a-zA-Z]/', '', $invoice['document']);
                $number = preg_replace('/[^0-9]/', '', $invoice['document']);

                return [
                    'id' => Invoice::byReferenceDetails($prefix, $number)->first()->id,
                    'amount_applied' => $invoice['amount'],
                ];
            })->toArray(),
        ]));

        return $payment;
    }

    protected function createInvoice($transaction)
    {
        $invoice = InvoiceService::createInvoice(new CreateInvoiceDto([
            'is_draft' => false,
            'customer_id' => $this->setupValues['customer']->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('INVOICE')->value,
            'payment_method_id' => PaymentMethodEnum::getEnumCase('CASH')->value,
            'payment_term_id' => PaymentTermFactory::new()->create()->id,
            'invoice_date' => $transaction['date'],
            // 'invoice_due_date' => $transaction['dueDate'],
            'invoiceDetails' => collect($transaction['lineItems'])->map(function ($line) {
                return [
                    'name' => $line['description'],
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unitPrice'],
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
                    'taxesIds' => collect($line['taxes'])->map(function ($tax) {
                        return $this->setupValues['taxes'][$tax]->id;
                    })->toArray(),
                ];
            })->toArray(),
        ]));

        $invoice->markApproved();

        return $invoice;
    }

    protected function createFromSetup($setup)
    {
        $customer = $this->createCustomerTest($setup['customer']);

        $taxDetails = $setup['taxConfiguration'];

        $taxes = [];
        foreach ($taxDetails as $tax) {
            $taxes[] = $this->createTaxTest($tax['name'], $tax['rate']);
        }

        $transactions = $setup['transactions'] ?? [];

        foreach ($transactions as $transaction) {
            $this->createTransaction($transaction);
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
