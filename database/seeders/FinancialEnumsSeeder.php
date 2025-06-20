<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Condoedge\Finance\Enums\GlTransactionTypeEnum;
use Condoedge\Finance\Models\PaymentTypeEnum;

/**
 * Financial Enums Seeder
 * 
 * Seeds the table-linked enum data for payment types and GL transaction types.
 * This ensures referential integrity and consistent enum values across environments.
 */
class FinancialEnumsSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedPaymentTypes();
        $this->seedGlTransactionTypes();
    }

    /**
     * Seed payment types table
     */
    private function seedPaymentTypes(): void
    {
        $paymentTypes = [
            [
                'id' => PaymentTypeEnum::CASH->value,
                'name' => 'cash',
                'label' => PaymentTypeEnum::CASH->label(),
                'code' => PaymentTypeEnum::CASH->code(),
                'description' => 'Cash payment',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => PaymentTypeEnum::CHECK->value,
                'name' => 'check',
                'label' => PaymentTypeEnum::CHECK->label(),
                'code' => PaymentTypeEnum::CHECK->code(),
                'description' => 'Check payment requiring bank account information',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => PaymentTypeEnum::CREDIT_CARD->value,
                'name' => 'credit_card',
                'label' => PaymentTypeEnum::CREDIT_CARD->label(),
                'code' => PaymentTypeEnum::CREDIT_CARD->code(),
                'description' => 'Credit card payment',
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => PaymentTypeEnum::BANK_TRANSFER->value,
                'name' => 'bank_transfer',
                'label' => PaymentTypeEnum::BANK_TRANSFER->label(),
                'code' => PaymentTypeEnum::BANK_TRANSFER->code(),
                'description' => 'Bank wire transfer requiring account information',
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => PaymentTypeEnum::CREDIT_NOTE->value,
                'name' => 'credit_note',
                'label' => PaymentTypeEnum::CREDIT_NOTE->label(),
                'code' => PaymentTypeEnum::CREDIT_NOTE->code(),
                'description' => 'Credit note application (allows negative amounts)',
                'sort_order' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('fin_payment_types')->insertOrIgnore($paymentTypes);
    }

    /**
     * Seed GL transaction types table
     */
    private function seedGlTransactionTypes(): void
    {        $glTransactionTypes = [
            [
                'id' => GlTransactionTypeEnum::MANUAL_GL->value,
                'name' => 'manual_gl',
                'label' => GlTransactionTypeEnum::MANUAL_GL->label(),
                'code' => GlTransactionTypeEnum::MANUAL_GL->code(),
                'fiscal_period_field' => GlTransactionTypeEnum::MANUAL_GL->getFiscalPeriodOpenField(),
                'allows_manual_entry' => GlTransactionTypeEnum::MANUAL_GL->allowsManualAccountEntry(),
                'description' => 'Manual general ledger entries',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => GlTransactionTypeEnum::BANK->value,
                'name' => 'bank',
                'label' => GlTransactionTypeEnum::BANK->label(),
                'code' => GlTransactionTypeEnum::BANK->code(),
                'fiscal_period_field' => GlTransactionTypeEnum::BANK->getFiscalPeriodOpenField(),
                'allows_manual_entry' => GlTransactionTypeEnum::BANK->allowsManualAccountEntry(),
                'description' => 'Bank transaction entries',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => GlTransactionTypeEnum::RECEIVABLE->value,
                'name' => 'receivable',
                'label' => GlTransactionTypeEnum::RECEIVABLE->label(),
                'code' => GlTransactionTypeEnum::RECEIVABLE->code(),
                'fiscal_period_field' => GlTransactionTypeEnum::RECEIVABLE->getFiscalPeriodOpenField(),
                'allows_manual_entry' => GlTransactionTypeEnum::RECEIVABLE->allowsManualAccountEntry(),
                'description' => 'Accounts receivable transaction entries',
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => GlTransactionTypeEnum::PAYABLE->value,
                'name' => 'payable',
                'label' => GlTransactionTypeEnum::PAYABLE->label(),
                'code' => GlTransactionTypeEnum::PAYABLE->code(),
                'fiscal_period_field' => GlTransactionTypeEnum::PAYABLE->getFiscalPeriodOpenField(),
                'allows_manual_entry' => GlTransactionTypeEnum::PAYABLE->allowsManualAccountEntry(),
                'description' => 'Accounts payable transaction entries',
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('fin_gl_transaction_types')->insertOrIgnore($glTransactionTypes);
    }
}
