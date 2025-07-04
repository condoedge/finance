<?php

namespace Condoedge\Finance\Database\Seeders;

use Condoedge\Finance\Enums\GlTransactionTypeEnum;
use Condoedge\Finance\Facades\AccountSegmentService;
use Condoedge\Finance\Facades\InvoiceTypeEnum;
use Condoedge\Finance\Models\GlTransactionType;
use Condoedge\Finance\Models\InvoiceStatus;
use Condoedge\Finance\Models\InvoiceStatusEnum;
use Condoedge\Finance\Models\InvoiceType;
use Condoedge\Finance\Models\InvoiceTypeEnum as ModelsInvoiceTypeEnum;
use Condoedge\Finance\Models\PaymentInstallment;
use Condoedge\Finance\Models\PaymentInstallmentEnum;
use Condoedge\Finance\Models\PaymentMethod;
use Condoedge\Finance\Models\PaymentMethodEnum;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run()
    {
        collect(InvoiceTypeEnum::getEnumClass()::cases())->each(function (ModelsInvoiceTypeEnum $enum) {
            $type = new InvoiceType();

            if (InvoiceType::find($enum->value)) {
                return null;
            }

            $type->id = $enum->value;
            $type->name = $enum->label();
            $type->prefix = $enum->prefix();
            $type->sign_multiplier = $enum->signMultiplier();
            $type->next_number = 1;
            $type->save();
        });

        collect(InvoiceStatusEnum::cases())->each(function ($enum) {
            $type = new InvoiceStatus();

            if (InvoiceStatus::find($enum->value)) {
                return null;
            }

            $type->id = $enum->value;
            $type->name = $enum->label();
            $type->save();
        });

        collect(PaymentMethodEnum::cases())->each(function ($enum) {
            $type = new PaymentMethod();

            if (PaymentMethod::find($enum->value)) {
                return null;
            }

            $type->id = $enum->value;
            $type->name = $enum->label();
            $type->code = $enum->code();
            $type->save();
        });

        collect(PaymentInstallmentEnum::cases())->each(function ($enum) {
            $type = new PaymentInstallment();

            if (PaymentInstallment::find($enum->value)) {
                return null;
            }

            $type->id = $enum->value;
            $type->name = $enum->label();
            $type->code = $enum->code();
            $type->save();
        });

        collect(GlTransactionTypeEnum::cases())->each(function ($enum) {
            $type = new GlTransactionType();

            if (GlTransactionType::find($enum->value)) {
                return null;
            }

            $type->id = $enum->value;
            $type->name = $enum->label();
            $type->label = $enum->label();
            $type->code = $enum->code();
            $type->fiscal_period_field = $enum->getFiscalPeriodOpenField();
            $type->allows_manual_entry = $enum->allowsManualAccountEntry();
            $type->description = '';
            $type->save();
        });

        AccountSegmentService::createDefaultSegments();
    }
}
