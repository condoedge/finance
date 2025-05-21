<?php

namespace Condoedge\Finance\Models\Dto\Payments;

use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Casts\SafeDecimalCast;
use WendellAdriel\ValidatedDTO\Attributes\Rules;
use WendellAdriel\ValidatedDTO\Casting\CarbonCast;
use WendellAdriel\ValidatedDTO\Casting\FloatCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class CreateCustomerPaymentDto extends ValidatedDTO
{
    use EmptyDefaults;

    #[Rules(['required', 'integer', 'exists:fin_customers,id'])]
    public int $customer_id;

    #[Rules(['required', 'date'])]
    public \Carbon\Carbon|string $payment_date;

    #[Rules(['required', 'numeric', 'min:0'])]
    public SafeDecimal $amount;

    public function casts(): array
    {
        return [
            'payment_date' => new CarbonCast,
            'amount' => new SafeDecimalCast,
            'customer_id' => new IntegerCast,
        ];
    }

    // To be automatically documentated by the API DOCS
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:fin_customers,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}