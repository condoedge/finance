<?php

namespace Condoedge\Finance\Kompo\SegmentManagement;

use Condoedge\Finance\Facades\AccountSegmentService;
use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Utils\Kompo\Common\Form;

class SegmentValidationPage extends Form
{
    public function render()
    {
        $issues = AccountSegmentService::validateSegmentStructure();
        $stats = AccountSegmentService::getSegmentStatistics();

        return _Rows(
            // Validation results
            $issues ?
                _Rows(
                    collect($issues)->map(fn($issue) => _Html($issue)->class('text-red-600'))
                )->class('gap-2 mb-4') :
                _Card(
                    _Html('finance-segment-structure-valid')
                )->class('text-green-800 text-center font-semibold w-full bg-green-100 p-4 mb-4'),

            // Statistics
            _Columns(
                _BoxLabelNum('card', 'finance-total-segments', _Html($stats['total_segments']))
                    ->class('bg-warning')
                    ->col('col-md-6 col-xl-3 order-3 md:order-1'),
                _BoxLabelNum('tag', 'finance-total-values', _Html($stats['total_values']))
                    ->class('bg-level4')
                    ->col('col-md-6 col-xl-3 order-4 md:order-2'),
                _BoxLabelNum('money-recive', 'finance-active-values', _Html($stats['active_values']))
                    ->class('bg-level5')
                    ->id('campaign-details-total-sales')
                    ->col('col-md-6 col-xl-3 order-2 md:order-3'),
                _BoxLabelNum('dollar-circle', 'finance-total-accounts', _Html($stats['total_accounts']))
                    ->class('bg-level3')
                    ->col('col-md-6 col-xl-3 order-1 md:order-4'),
            ),

            // Coverage analysis
            _Card(
                _TitleMini('finance-segment-coverage')->class('mb-4'),

                new SegmentCoverageTable(),
            )
        )->class('space-y-4');
    }
}
