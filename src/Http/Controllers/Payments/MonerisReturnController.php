<?php

namespace Condoedge\Finance\Http\Controllers\Payments;

use Condoedge\Finance\Billing\Core\PaymentContext;
use Condoedge\Finance\Billing\Providers\Moneris\MonerisPaymentProvider;
use Condoedge\Finance\Facades\PaymentProcessor;
use Condoedge\Finance\Models\PaymentMethodEnum;
use Condoedge\Finance\Models\PaymentTrace;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * Handles Moneris hosted-checkout redirect-back.
 *
 * Moneris MCO doesn't pass our custom params back — the return URL is configured
 * globally per checkout profile and only carries the ticket. We reconstruct
 * context from the in-flight PaymentTrace row created when the initial
 * processPayment() call returned a pending+REDIRECT result.
 *
 * Route name: finance.payments.moneris.return
 */
class MonerisReturnController extends Controller
{
    public function handle(Request $request)
    {
        $ticket = $request->input('ticket') ?? $request->input('response_code'); // some MCO setups vary
        if (!$ticket) {
            Log::warning('Moneris return: missing ticket', $request->all());
            return $this->redirectWithError('missing_ticket');
        }

        // The pending trace was created during the preload leg — it carries
        // payable info, payment method, and team. No need for Moneris to echo it.
        $trace = PaymentTrace::forExternalReference($ticket)->first();
        if (!$trace) {
            Log::warning('Moneris return: no payment trace for ticket', ['ticket' => $ticket]);
            return $this->redirectWithError('unknown_ticket');
        }

        $payable = $this->loadPayable($trace->payable_type, (int) $trace->payable_id);
        if (!$payable) {
            Log::warning('Moneris return: payable disappeared', [
                'ticket' => $ticket,
                'payable_type' => $trace->payable_type,
                'payable_id' => $trace->payable_id,
            ]);
            abort(404);
        }

        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: $trace->payment_method_id instanceof PaymentMethodEnum
                ? $trace->payment_method_id
                : PaymentMethodEnum::from((int) $trace->payment_method_id),
            paymentData: ['ticket' => $ticket],
        );

        try {
            // The provider's processPayment branches on ticket presence — second
            // leg performs the receipt lookup and returns success/failure.
            $provider = app(MonerisPaymentProvider::class);
            $result = $provider->processPayment($context);
            $result = PaymentProcessor::managePaymentResult($result, $context);
        } catch (\Throwable $e) {
            Log::error('Moneris return processing failed', [
                'ticket' => $ticket,
                'error' => $e->getMessage(),
            ]);
            return $this->redirectWithError('processing_error');
        }

        if ($result->success) {
            return redirect()->to($this->successUrlFor($payable))
                ->with('success', __('finance-paid-successfully'));
        }

        return $this->redirectWithError('declined');
    }

    private function loadPayable(?string $type, int $id)
    {
        if (!$type) {
            return null;
        }

        $class = \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($type) ?: $type;

        if (!class_exists($class)) {
            return null;
        }

        return $class::find($id);
    }

    private function successUrlFor($payable): string
    {
        // Best-effort: use the payable's invoice URL if it exposes one, else
        // fall back to the named invoice route, else "/".
        if (method_exists($payable, 'getInvoiceUrl')) {
            return $payable->getInvoiceUrl() ?? '/';
        }
        try {
            return route('invoices.show', ['id' => $payable->getPayableId()]);
        } catch (\Throwable) {
            return '/';
        }
    }

    private function redirectWithError(string $reason)
    {
        return redirect('/')
            ->withErrors(['payment' => __('error-finance-moneris-' . $reason)]);
    }
}
