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
use Condoedge\Finance\Models\PaymentMethod;
use Condoedge\Finance\Models\PaymentMethodEnum;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run()
    {
        collect(InvoiceTypeEnum::getEnumClass()::cases())->each(function (ModelsInvoiceTypeEnum $enum) {
            $type = InvoiceType::find($enum->value);

            if (!$type) {
                $type = new InvoiceType();
                $type->id = $enum->value;
            }

            $type->name = collect(array_keys(config('kompo.locales')))->mapWithKeys(function($locale) use ($enum) { 
                return [$locale => __($enum->rawTranslationKey(), locale: $locale)];
            })->toArray();
            $type->prefix = $enum->prefix();
            $type->sign_multiplier = $enum->signMultiplier();
            $type->next_number = 1;
            $type->save();
        });

        collect(InvoiceStatusEnum::cases())->each(function ($enum) {
            $type = InvoiceStatus::find($enum->value);

            if (!$type) {
                $type = new InvoiceStatus();
                $type->id = $enum->value;
            }
            
            $type->name = collect(array_keys(config('kompo.locales')))->mapWithKeys(function($locale) use ($enum) { 
                return [$locale => __($enum->rawTranslationKey(), locale: $locale)];
            })->toArray();
            $type->code = $enum->code();
            $type->save();
        });

        collect(PaymentMethodEnum::cases())->each(function ($enum) {
            $type = PaymentMethod::find($enum->value);    

            if (!$type) {
                $type = new PaymentMethod();
                $type->id = $enum->value;
            }

            $type->name = collect(array_keys(config('kompo.locales')))->mapWithKeys(function($locale) use ($enum) { 
                return [$locale => __($enum->rawTranslationKey(), locale: $locale)];
            })->toArray();
            $type->code = $enum->code();
            $type->save();
        });

        collect(GlTransactionTypeEnum::cases())->each(function ($enum) {
            $type = GlTransactionType::find($enum->value);

            if (!$type) {
                $type = new GlTransactionType();
                $type->id = $enum->value;
            }

            $type->name = collect(array_keys(config('kompo.locales')))->mapWithKeys(function($locale) use ($enum) { 
                return [$locale => __($enum->rawTranslationKey(), locale: $locale)];
            })->toArray();
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
