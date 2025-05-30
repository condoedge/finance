<?php

namespace Condoedge\Finance\Database\Factories;

use Condoedge\Finance\Models\Payable\Bill;
use Condoedge\Finance\Models\Payable\BillStatusEnum;
use Condoedge\Finance\Models\Payable\BillTypeEnum;
use Condoedge\Finance\Models\PaymentTypeEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillFactory extends Factory
{
    protected $model = Bill::class;

    public function definition()
    {
        $billDate = $this->faker->dateTimeBetween('-6 months', 'now');
        
        return [
            'bill_type_id' => BillTypeEnum::BILL->value,
            'bill_date' => $billDate,
            'bill_due_date' => $this->faker->dateTimeBetween($billDate, '+30 days'),
            'payment_type_id' => PaymentTypeEnum::CASH->value,
            'is_draft' => false,
            'bill_status_id' => BillStatusEnum::PENDING->value,
        ];
    }

    public function draft()
    {
        return $this->state([
            'is_draft' => true,
            'bill_status_id' => BillStatusEnum::DRAFT->value,
        ]);
    }

    public function pending()
    {
        return $this->state([
            'is_draft' => false,
            'bill_status_id' => BillStatusEnum::PENDING->value,
        ]);
    }

    public function paid()
    {
        return $this->state([
            'is_draft' => false,
            'bill_status_id' => BillStatusEnum::PAID->value,
        ]);
    }

    public function credit()
    {
        return $this->state([
            'bill_type_id' => BillTypeEnum::CREDIT->value,
        ]);
    }
}
