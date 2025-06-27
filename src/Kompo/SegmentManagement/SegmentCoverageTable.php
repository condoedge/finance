<?php

namespace Condoedge\Finance\Kompo\SegmentManagement;

use Condoedge\Finance\Facades\AccountSegmentService;
use Condoedge\Utils\Kompo\Common\Table;

class SegmentCoverageTable extends Table
{
    public function query()
    {
        return AccountSegmentService::getSegmentsCoverageData();
    }

    public function headers()
    {
        return [
            _Th('finance-segment-description'),
            _Th('finance-total-values'),
            _Th('finance-active-values'),
            _Th('finance-usage-percentage'),
        ];
    }

    public function render($segmentData)
    {
        $segmentData = (object) $segmentData;

        return _TableRow(
            _Html($segmentData->segment_description),
            _Html($segmentData->total_values),
            _Html($segmentData->active_values),
            _Flex(
                _ProgressBar($segmentData->usage_percentage / 100)
                    ->class('w-[130px]'),
                
                _Html(sprintf('%.2f%%', $segmentData->usage_percentage)),
            )->class('gap-4')
        );
    }
}