<?php

namespace Condoedge\Finance\Models\Dto\Payments;

use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Casts\SafeDecimalCast;
use Condoedge\Finance\Rule\SafeDecimalRule;
use WendellAdriel\ValidatedDTO\Casting\CarbonCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

/**
 * Create Customer Payment DTO
 *
 * Used to create new customer payments. Payments can be applied to specific invoices
 * or remain as credit on the customer account.
 *
 * @property int $customer_id The customer making the payment
 * @property \Carbon\Carbon|string $payment_date Date the payment was received
 * @property SafeDecimal $amount Payment amount with decimal precision
 */
class CreateCustomerPaymentDto extends ValidatedDTO
{
    use EmptyDefaults;

    public int $customer_id;

    public \Carbon\Carbon|string $payment_date;

    public SafeDecimal $amount;

    public ?string $external_reference;

    public function casts(): array
    {
        return [
            'payment_date' => new CarbonCast(),
            'amount' => new SafeDecimalCast(),
            'customer_id' => new IntegerCast(),
            'external_reference' => new StringCast(),
        ];
    }

    // To be automatically documentated by the API DOCS
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:fin_customers,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', new SafeDecimalRule(true)],
            'external_reference' => ['nullable', 'string'],
        ];
    }
}
