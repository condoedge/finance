<?php
namespace Condoedge\Finance\Kompo\FiscalSetup;

use Condoedge\Finance\Enums\GlTransactionTypeEnum;
use Condoedge\Finance\Facades\FiscalYearService;
use Condoedge\Finance\Models\FiscalPeriod;
use Condoedge\Utils\Kompo\Common\WhiteTable;

class FiscalSetupPeriods extends WhiteTable
{
    public $perPage = 10;

    public function query()
    {
        return FiscalPeriod::forTeam()->orderBy('fiscal_year', 'desc')
            ->orderBy('period_number', 'desc')
            ->get();
    }

    public function headers()
    {
        return [
            _Th('translate.fiscal-year'),
            _Th('translate.period-number'),
            _Th('translate.start-end'),
            _Th('translate.is-open-gl'),
            _Th('translate.is-open-bnk'),
            _Th('translate.is-open-rm'),
            _Th('translate.is-open-pm'),
            _Th('translate.actions')->class('w-8'),
        ];
    }

    public function render($period)
    {
        return _TableRow(
            _Html($period->fiscal_year),
            _Html($period->period_number),
            _Rows(
                _Html($period->start_date->format('Y-m-d')),
                _Html($period->end_date->format('Y-m-d')),
            )->class('text-sm gap-1 text-gray-700'),

            _Checkbox()->name('toggle' . GlTransactionTypeEnum::MANUAL_GL->value)->class('!mb-0')->default($period->isOpenForModule(GlTransactionTypeEnum::MANUAL_GL))
                ->selfPost('toggleModule', ['module' => GlTransactionTypeEnum::MANUAL_GL->value, 'period_id' => $period->id]),

            _Checkbox()->name('toggle' . GlTransactionTypeEnum::BANK->value)->class('!mb-0')->default($period->isOpenForModule(GlTransactionTypeEnum::BANK))
                ->selfPost('toggleModule', ['module' => GlTransactionTypeEnum::BANK->value, 'period_id' => $period->id]),

            _Checkbox()->name('toggle' . GlTransactionTypeEnum::RECEIVABLE->value)->class('!mb-0')->default($period->isOpenForModule(GlTransactionTypeEnum::RECEIVABLE))
                ->selfPost('toggleModule', ['module' => GlTransactionTypeEnum::RECEIVABLE->value, 'period_id' => $period->id]),

            _Checkbox()->name('toggle' . GlTransactionTypeEnum::PAYABLE->value)->class('!mb-0')->default($period->isOpenForModule(GlTransactionTypeEnum::PAYABLE))
                ->selfPost('toggleModule', ['module' => GlTransactionTypeEnum::PAYABLE->value, 'period_id' => $period->id]),

            _TripleDotsDropdown(),
        );
    }

    public function toggleModule()
    {
        $periodId = request('period_id');
        $module = request('module');

        $value = request('toggle' . $module);

        if ($value) {
            // If the value is true, we open the period for the module
            FiscalYearService::openPeriod($periodId, [$module]);
        } else {
            // If the value is false, we close the period for the module
            FiscalYearService::closePeriod($periodId, [$module]);
        }
    }
}