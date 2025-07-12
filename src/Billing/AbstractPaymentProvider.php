<?php

namespace Condoedge\Finance\Billing;

use Condoedge\Finance\Facades\PaymentService;
use Condoedge\Finance\Models\Dto\Payments\CreateCustomerPaymentDto;
use Condoedge\Finance\Models\Dto\Payments\CreateCustomerPaymentForInvoiceDto;
use Condoedge\Finance\Models\PaymentInstallmentPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

abstract class AbstractPaymentProvider implements PaymentGatewayInterface
{
    abstract protected function getDataFromResponse($key, $data = null);
    abstract public function createSale($request);
    abstract public function checkIfPaymentWasSuccessful();
    abstract public function availablePaymentMethods(): array;
    
    public function executeSale($request, $onSuccess = null)
    {
        $this->initializeContext($request);

        $this->createSale($request);

        if ($this->checkIfPaymentWasSuccessful()) {
            $this->onSuccessTransaction($this->getDataFromResponse('amount'), $this->getDataFromResponse('referenceUUID'));

            if (is_callable($onSuccess)) {
                $onSuccess($this->saleResponse);
            }

            return true;
        } else {
            \Log::critical('ERROR!!', $this->saleResponse);

            abort(403, __('error-payment-failed'));
        }
    }

    public function onSuccessTransaction($amount, $externalReference)
    {
        return DB::transaction(function () use ($amount, $externalReference) {
            $payable = $this->findPayable($result->metadata);

            $payment = PaymentService::createPayment(new CreateCustomerPaymentDto([
                'payment_date' => now(),
                'amount' => $amount,
                'customer_id' => $payable->getCustomerId(),
                'external_reference' => $externalReference,
            ]));

            $payable->onPaymentSuccess($payment);

            // if ($this->invoice->invoice_due_amount->equals(0)) {
            //     $this->invoice->onCompletePayment();
            // } else {
            //     $this->invoice->onPartialPayment();
            // }

            return true;
        });
    }

    private function findPayable(array $metadata): ?PayableInterface
    {
        if (empty($metadata['payable_type']) || empty($metadata['payable_id'])) {
            return null;
        }
        
        $payableClass = $metadata['payable_type'];
        
        if (!class_exists($payableClass)) {
            return null;
        }
        
        $payable = $payableClass::find($metadata['payable_id']);
        
        if ($payable instanceof PayableInterface) {
            return $payable;
        }
        
        return null;
    }

    public function createPaymentRecordAssociated($amount, $externalReference)
    {
        PaymentService::createPayment(new CreateCustomerPaymentDto([
            'payment_date' => now(),
            'amount' => $amount,
            'invoice_id' => $this->invoice->id,
            'external_reference' => $externalReference,
        ]));


        PaymentService::createPaymentAndApplyToInvoice(new CreateCustomerPaymentForInvoiceDto([
            'payment_date' => now(),
            'amount' => $amount,
            'invoice_id' => $this->invoice->id,
            'external_reference' => $externalReference,
        ]));

        $this->invoice->refresh();

        return true;
    }

    /** @var \Condoedge\Finance\Models\Invoice */
    protected $invoice;
    protected $installment_ids;
    protected $saleResponse;

    public function initializeContext(array $context = []): void
    {
        if (isset($context['invoice'])) {
            $this->invoice = $context['invoice'];
        }

        if (isset($context['installment_ids'])) {
            $this->installment_ids = $context['installment_ids'];
        }
    }

    protected function ensureInvoiceIsSet()
    {
        if (!$this->invoice || !$this->invoice->payment_method_id) {
            Log::critical('BNA Payment Provider: Invoice is not set for payment data configuration.');
            abort(403, __('error-payment-cannot-be-completed'));
        }
    }

    /**
     * Get the payable lines for the invoice.
     *
     * @return \Illuminate\Support\Collection<\Condoedge\Finance\Models\Dto\Invoices\PayableLineDto>
     */
    protected function getPayableLines()
    {
        if ($this->installment_ids) {
            return collect($this->installment_ids)->map(function ($installmentId) {
                $installmentPeriod = PaymentInstallmentPeriod::findOrFail($installmentId);

                return new \Condoedge\Finance\Models\Dto\Invoices\PayableLineDto([
                    'description' => __('finance-installment-period', [
                        'number' => $installmentPeriod->installment_number,
                    ]),
                    'sku' => 'pip.' . $installmentPeriod->id,
                    'price' => $installmentPeriod->due_amount->toFloat(),
                    'quantity' => 1,
                    'amount' => $installmentPeriod->due_amount->toFloat(),
                ]);
            });
        }

        return $this->invoice->invoiceDetails->map(function ($invoiceDetail) {
            return new \Condoedge\Finance\Models\Dto\Invoices\PayableLineDto([
                'description' => $invoiceDetail->name,
                'sku' => 'id.' . $invoiceDetail->id,
                'price' => $invoiceDetail->total_amount->divide($invoiceDetail->quantity)->toFloat(),
                'quantity' => $invoiceDetail->quantity,
                'amount' => $invoiceDetail->total_amount->toFloat(),
            ]);
        });
    }
}
