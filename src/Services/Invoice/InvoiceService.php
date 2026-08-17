<?php

namespace Condoedge\Finance\Services\Invoice;

use Condoedge\Finance\Billing\Core\PaymentContext;
use Condoedge\Finance\Billing\Core\PaymentResult;
use Condoedge\Finance\Events\InvoiceSent;
use Condoedge\Finance\Facades\CustomerModel;
use Condoedge\Finance\Facades\CustomerService;
use Condoedge\Finance\Facades\InvoiceDetailService;
use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Facades\PaymentProcessor;
use Condoedge\Finance\Facades\PaymentService;
use Condoedge\Finance\Facades\PaymentTermService;
use Condoedge\Finance\Models\Customer;
use Condoedge\Finance\Models\Dto\Invoices\ApproveInvoiceDto;
use Condoedge\Finance\Models\Dto\Invoices\ApproveManyInvoicesDto;
use Condoedge\Finance\Models\Dto\Invoices\CreateCreditNoteDto;
use Condoedge\Finance\Models\Dto\Invoices\CreateInvoiceDto;
use Condoedge\Finance\Models\Dto\Invoices\CreateOrUpdateInvoiceDetail;
use Condoedge\Finance\Models\Dto\Invoices\PayInvoiceDto;
use Condoedge\Finance\Models\Dto\Invoices\UpdateInvoiceDto;
use Condoedge\Finance\Models\Dto\Payments\CreateApplyForInvoiceDto;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\InvoiceTypeEnum;
use Condoedge\Finance\Models\MorphablesEnum;
use Condoedge\Finance\Models\PaymentInstallmentPeriod;
use Condoedge\Finance\Models\PaymentMethodEnum;
use Condoedge\Finance\Models\PaymentTerm;
use Condoedge\Finance\Models\SegmentValue;
use Condoedge\Utils\Models\ContactInfo\Maps\Address;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Invoice Service Implementation
 *
 * Handles all invoice business logic including creation, updates,
 * approval workflows, and calculations.
 *
 * This implementation can be easily overridden by binding a custom
 * implementation to the InvoiceServiceInterface in your service provider.
 */
class InvoiceService implements InvoiceServiceInterface
{
    public function upsertInvoice(CreateInvoiceDto|UpdateInvoiceDto $dto): Invoice
    {
        if ($dto instanceof CreateInvoiceDto) {
            return $this->createInvoice($dto);
        } elseif ($dto instanceof UpdateInvoiceDto) {
            return $this->updateInvoice($dto);
        }

        throw new \InvalidArgumentException('Invalid DTO type provided');
    }

    /**
     * Create a new invoice with full business logic
     */
    public function createInvoice(CreateInvoiceDto $dto): Invoice
    {
        return DB::transaction(function () use ($dto) {
            // Create base invoice
            $invoice = $this->createBaseInvoice($dto);

            // Apply customer preferences and data
            $this->applyCustomerDataToInvoice($invoice, $dto->customer_id);

            // Save the invoice to get ID for details
            $invoice->save();

            // Create invoice details
            $this->createInvoiceDetails($invoice, $dto->invoiceDetails);

            if ($invoice->payment_method_id) {
                $this->setupInvoiceAccount($invoice);
            }

            if ($invoice->payment_term_id) {
                PaymentTermService::manageNewPaymentTermIntoInvoice($invoice);
            }

            // Refresh to get calculated fields
            $invoice->refresh();

            return $invoice;
        });
    }

    /**
     * Raise a credit note, optionally against an invoice.
     *
     * Lines are stated as they read on the invoice — positive for a charge being credited,
     * negative for a rebate being reversed — and the whole set is mirrored once here.
     *
     * A single negation, never abs(): an inscription invoice carries rebate lines, and
     * stripping their sign would credit 100 + 30 against a 70 invoice.
     */
    public function createCreditNote(CreateCreditNoteDto $dto): Invoice
    {
        return DB::transaction(function () use ($dto) {
            $source = $dto->credited_invoice_id ? InvoiceModel::findOrFail($dto->credited_invoice_id) : null;

            $lines = $dto->lines ?: $this->cloneLinesFrom($source);

            $credit = $this->createInvoice(new CreateInvoiceDto([
                'customer_id' => $dto->customer_id ?: $source->customer_id,
                'team_id' => $dto->team_id ?: $source?->team_id,
                'invoice_type_id' => InvoiceTypeEnum::CREDIT->value,
                'invoice_date' => $dto->invoice_date,
                'invoiceDetails' => array_map(fn ($line) => [
                    ...$line,
                    'unit_price' => -1 * (float) $line['unit_price'],
                ], $lines),
            ]));

            if ($source) {
                $credit->credited_invoice_id = $source->id;
                $credit->save();
            }

            $this->applyApprovalToInvoice($credit);
            $credit->refresh();

            // Checked here rather than in the DTO because only now is the figure exact:
            // taxes are computed, and the clone path never states its lines up front.
            if ($source && $dto->apply_to_invoice
                && $source->invoice_due_amount->lessThan($credit->abs_invoice_due_amount)) {
                throw ValidationException::withMessages([
                    'lines' => __('finance-credit-exceeds-invoice-due'),
                ]);
            }

            if ($source && $dto->apply_to_invoice) {
                PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
                    'invoice_id' => $source->id,
                    'amount_applied' => $credit->abs_invoice_due_amount,
                    'apply_date' => $dto->invoice_date,
                    'applicable' => $credit,
                    'applicable_type' => MorphablesEnum::CREDIT->value,
                ]));

                $credit->refresh();
            }

            return $credit;
        });
    }

    /**
     * Mirror an invoice's lines, keeping revenue accounts, taxes and signs so the credit
     * reverses exactly what was charged — rebates and taxes included.
     */
    protected function cloneLinesFrom(?Invoice $source): array
    {
        if (!$source) {
            return [];
        }

        return $source->invoiceDetails->map(fn ($detail) => [
            'name' => $detail->name,
            'description' => $detail->description,
            'quantity' => $detail->quantity,
            'unit_price' => $detail->unit_price->toFloat(),
            'revenue_account_id' => $detail->revenue_account_id,
            'taxesIds' => $detail->invoiceTaxes->pluck('tax_id')->all(),
        ])->all();
    }

    /**
     * Update existing invoice
     */
    public function updateInvoice(UpdateInvoiceDto $dto): Invoice
    {
        return DB::transaction(function () use ($dto) {
            $invoice = InvoiceModel::findOrFail($dto->id);

            $oldPaymentTermId = $invoice->getOriginal('payment_term_id');

            // Update base fields
            $this->updateInvoiceFields($invoice, $dto);

            // Handle invoice details updates/creation
            if (isset($dto->invoiceDetails)) {
                $this->updateInvoiceDetails($invoice, $dto->invoiceDetails);
            }

            $originalPaymentTerm = PaymentTerm::find($oldPaymentTermId);
            if ($invoice->paymentTerm?->id != $originalPaymentTerm?->id) {
                PaymentTermService::manageNewPaymentTermIntoInvoice($invoice, $originalPaymentTerm?->term_type);
            }

            if ($invoice->payment_method_id) {
                $this->setupInvoiceAccount($invoice);
            }

            $invoice->refresh();

            return $invoice;
        });
    }

    // We are already setting the customer address on insert using a trigger.
    // But if the customer doesn't have an address at that moment, we are setting it with this method after.
    public function setAddress(Invoice $invoice, array $addressData): void
    {
        if ($invoice->address && !$invoice->is_draft) {
            throw new Exception('error-cannot-update-address-on-non-draft-invoice');
        }

        DB::transaction(function () use ($invoice, $addressData) {
            // $invoice->address()->delete(); // Remove existing address if any
            Address::createMainForFromRequest($invoice, $addressData);

            // This is the main customer of the historical customer
            $customer = Customer::find($invoice->customer_id);
            if (!$customer->address) {
                Address::createMainForFromRequest($customer, $addressData);
            }
        });
    }

    /**
     * Approve a single invoice
     */
    public function approveInvoice(ApproveInvoiceDto $dto): Invoice
    {
        return DB::transaction(function () use ($dto) {
            $invoice = InvoiceModel::findOrFail($dto->invoice_id);

            if ($dto->address) {
                $this->setAddress($invoice, $dto->address->toArray() ?? []);
            }

            // Apply approval
            $this->applyApprovalToInvoice($invoice);

            return $invoice;
        });
    }

    public function sendInvoice($id, $customEmail = null): void
    {
        $invoice = InvoiceModel::findOrFail($id);

        if ($invoice->is_draft) {
            abort(403, __('error-finance-cannot-send-a-draft-invoice'));
            // throw new InvalidArgumentException('error-finance-cannot-send-a-draft-invoice');
        }

        if (!$invoice->mainCustomer?->email) {
            abort(403, __('error-invoice-customer-email-not-found'));
            // throw new InvalidArgumentException('error-invoice-customer-email-not-found');
        }

        // This will dispatch the InvoiceSent event
        // and send the email to the customer
        event(new InvoiceSent($invoice, $customEmail));

        $invoice->markAsSent();
    }

    public function payInvoice(PayInvoiceDto $dto): PaymentResult
    {
        return DB::transaction(function () use ($dto) {
            $invoice = InvoiceModel::findOrFail($dto->invoice_id);

            if ($invoice->is_draft) {
                throw new Exception('error-finance-cannot-pay-draft-invoice');
            }

            if (isset($dto->address)) {
                $this->setAddress($invoice, $dto->address->toArray());
            }

            $this->updateInvoice(new UpdateInvoiceDto([
                'id' => $invoice->id,
                'payment_method_id' => $dto->payment_method_id,
                'payment_term_id' => $dto->payment_term_id,
            ]));

            if ($dto->pay_next_installment) {
                $nextInstallment = $invoice->getNextInstallmentPeriod();

                $dto->installment_id = $nextInstallment?->id;
            }

            $invoice->refresh();

            $paymentInstallment = $dto->installment_id ? PaymentInstallmentPeriod::findOrFail($dto->installment_id) : null;

            $result = PaymentProcessor::processPayment(new PaymentContext(payable: $paymentInstallment ?? $invoice, paymentMethod: $invoice->payment_method_id, paymentData: request()->all()));

            return $result;
        });
    }

    /**
     * Approve multiple invoices
     */
    public function approveMany(ApproveManyInvoicesDto $dto): Collection
    {
        return DB::transaction(function () use ($dto) {
            $invoices = InvoiceModel::whereIn('id', $dto->invoices_ids)->get();

            // Approve all if validation passes
            foreach ($invoices as $invoice) {
                $this->approveInvoice(new ApproveInvoiceDto([
                    'invoice_id' => $invoice->id,
                ]));
            }

            return $invoices;
        });
    }

    /* PROTECTED METHODS - Can be overridden for customization */

    /**
     * Auto-select the option when only one is possible.
     * Anything that isn't a usable id is discarded so it never reaches the foreign key column.
     */
    protected function soleOptionId(?array $possibleOptions): ?int
    {
        $possibleOptions = array_values($possibleOptions ?? []);

        if (count($possibleOptions) != 1 || !is_numeric($possibleOptions[0])) {
            return null;
        }

        return (int) $possibleOptions[0];
    }

    /**
     * Create base invoice model
     */
    protected function createBaseInvoice(CreateInvoiceDto $dto): Invoice
    {
        $invoice = InvoiceModel::newInstance();
        $invoice->customer_id = $dto->customer_id;
        $invoice->invoice_date = $dto->invoice_date;
        $invoice->invoice_type_id = $dto->invoice_type_id;
        $invoice->is_draft = true; // Always it starts as draft
        $invoice->possible_payment_terms = $dto->possible_payment_terms ?? [];
        $invoice->possible_payment_methods = $dto->possible_payment_methods ?? [];
        $invoice->payment_method_id = $dto->payment_method_id ?? $this->soleOptionId($invoice->possible_payment_methods);
        $invoice->payment_term_id = $dto->payment_term_id ?? $this->soleOptionId($invoice->possible_payment_terms);
        $invoice->invoiceable_type = $dto->invoiceable_type;
        $invoice->invoiceable_id = $dto->invoiceable_id;
        $invoice->team_id = $dto->team_id;

        // A credit note is never due: a due date is what makes it fall overdue.
        if ($invoice->paymentTerm && !$invoice->isRefund()) {
            $invoice->invoice_due_date = $invoice->paymentTerm->calculateDueDate($invoice->invoice_date);
        }

        return $invoice;
    }

    /**
     * Setup payment gateway for invoice using stateless approach
     */
    protected function setupInvoiceAccount(Invoice $invoice): void
    {
        $account = $this->resolveReceivableAccount($invoice->payment_method_id);
        $invoice->account_receivable_id = $account?->id;
        $invoice->save();
    }

    /**
     * Resolve the receivable GL account for a payment method
     * TODO: Implement proper account mapping per payment method
     */
    protected function resolveReceivableAccount(PaymentMethodEnum $paymentMethod): ?GlAccount
    {
        return GlAccount::getFromLatestSegmentValue(SegmentValue::first()?->id);
    }

    /**
     * Apply customer data to invoice
     */
    protected function applyCustomerDataToInvoice(Invoice $invoice, int $customerId): void
    {
        $customer = CustomerModel::find($customerId);

        // Fill invoice with customer preferences/data
        CustomerService::fillInvoiceWithCustomerData($customer, $invoice);
    }

    /**
     * Create invoice details from array
     */
    protected function createInvoiceDetails(Invoice $invoice, array $detailsData): void
    {
        foreach ($detailsData as $detail) {
            InvoiceDetailService::createInvoiceDetail(new CreateOrUpdateInvoiceDetail(array_merge($detail, [
                'invoice_id' => $invoice->id,
            ])));
        }
    }

    /**
     * Update invoice fields
     */
    protected function updateInvoiceFields(Invoice $invoice, UpdateInvoiceDto $dto): void
    {
        $colsJustUpdatablesOnDraft = [
            'possible_payment_terms',
            'possible_payment_methods',
            'invoice_date',
        ];

        $tryingToUpdateInvalid = collect($colsJustUpdatablesOnDraft)->filter(function ($col) use ($dto) {
            if (!($dto->{$col} ?? null)) {
                return false;
            }

            return true;
        });

        if (!$invoice->is_draft && $tryingToUpdateInvalid->isNotEmpty()) {
            throw new Exception(__('finance-cannot-update-non-draft-invoice'));
        }

        if ($invoice->is_draft) {
            $invoice->possible_payment_terms = $dto->possible_payment_terms ?? $invoice->possible_payment_terms ?? [];
            $invoice->possible_payment_methods = $dto->possible_payment_methods ?? $invoice->possible_payment_methods ?? [];
            $invoice->invoice_date = $dto->invoice_date ?? $invoice->invoice_date;
        }

        $invoice->payment_term_id = $dto->payment_term_id ?? $this->soleOptionId($invoice->possible_payment_terms);
        $invoice->payment_method_id = $dto->payment_method_id ?? $this->soleOptionId($invoice->possible_payment_methods);

        if ($invoice->isDirty('payment_term_id') && $invoice->paymentTerm && !$invoice->isRefund()) {
            $invoice->invoice_due_date = $invoice->paymentTerm->calculateDueDate($invoice->invoice_date);
        }

        $invoice->save();
    }

    /**
     * Update/create invoice details
     */
    protected function updateInvoiceDetails(Invoice $invoice, array $detailsData): void
    {
        foreach ($detailsData as $detail) {
            $id = $detail['id'] ?? null;

            $data = new CreateOrUpdateInvoiceDetail($detail + [
                'invoice_id' => $invoice->id,
            ]);

            if ($id) {
                InvoiceDetailService::updateInvoiceDetail($data);
            } else {
                InvoiceDetailService::createInvoiceDetail($data);
            }
        }
    }

    /**
     * Apply approval to invoice
     */
    protected function applyApprovalToInvoice(Invoice $invoice): void
    {
        if ($invoice->invoiceDetails()->count() == 0) {
            throw new Exception('finance-invoice-must-have-at-least-one-detail');
        }

        $invoice->is_draft = false;
        $invoice->approved_by = auth()->user()?->id ?? 1;
        $invoice->approved_at = now();
        $invoice->save();
    }
}
