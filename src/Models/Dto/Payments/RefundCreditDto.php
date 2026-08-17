<?php

namespace Condoedge\Finance\Models\Dto\Payments;

use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Casts\SafeDecimalCast;
use Condoedge\Finance\Facades\InvoiceModel;
use Illuminate\Validation\Validator;
use WendellAdriel\ValidatedDTO\Casting\CarbonCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

/**
 * Refund a credit note: money actually leaves, so a payment method is required.
 *
 * `amount` is the positive sum handed back. Applying it to invoices instead is a
 * different operation — see PaymentService::applyPaymentToInvoices().
 *
 * @property int $credit_id The credit note being refunded
 * @property SafeDecimal $amount Positive amount to hand back
 */
class RefundCreditDto extends ValidatedDTO
{
    use EmptyDefaults;

    public int $credit_id;

    public \Carbon\Carbon|string $payment_date;

    public SafeDecimal $amount;

    public int $payment_method_id;

    public function casts(): array
    {
        return [
            'credit_id' => new IntegerCast(),
            'payment_date' => new CarbonCast(),
            'amount' => new SafeDecimalCast(),
            'payment_method_id' => new IntegerCast(),
        ];
    }

    public function rules(): array
    {
        return [
            'credit_id' => ['required', 'integer', 'exists:fin_invoices,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method_id' => ['required', 'integer', 'exists:fin_payment_methods,id'],
        ];
    }

    public function after(Validator $validator): void
    {
        $credit = InvoiceModel::find($this->dtoData['credit_id'] ?? null);

        if (!$credit) {
            return;
        }

        if (!$credit->isRefund()) {
            $validator->errors()->add('credit_id', __('finance-only-a-credit-note-can-be-refunded'));

            return;
        }

        if ($credit->is_draft) {
            $validator->errors()->add('credit_id', __('finance-cannot-refund-a-draft-credit-note'));

            return;
        }

        $left = $credit->abs_invoice_due_amount;
        $amount = new SafeDecimal($this->dtoData['amount'] ?? 0);

        if ($amount->greaterThan($left)) {
            $validator->errors()->add('amount', __('finance-refund-exceeds-credit-left'));
        }
    }
}
