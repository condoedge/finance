<?php

namespace Condoedge\Finance\Kompo\SegmentManagement;

use Condoedge\Finance\Facades\AccountSegmentService;
use Condoedge\Finance\Kompo\SegmentManagement\SegmentsTable;
use Condoedge\Finance\Models\AccountSegment;
use \Condoedge\Utils\Kompo\Common\Form;

class SegmentStructurePage extends Form
{
    public function render()
    {
        $segments = AccountSegment::getAllOrdered();

        return _Rows(
            _Card(
                _FlexBetween(
                    _TitleMini('finance-current-segment-structure'),
                    _Button('finance-add-segment')->icon('plus')
                        ->selfGet('getSegmentStructureFormModal')->inModal()
                        ->disabled($segments->count() >= 10) // Reasonable limit
                )->class('mb-4'),

                new SegmentsTable() // Custom table component for segments
            ),

            // Current account format display
            _Card(
                _TitleMini('finance-account-format')->class('mb-2'),
                _Flex(
                    _Html(__('finance-current-format') . ':')->class('text-gray-600'),
                    _Html(AccountSegmentService::getAccountFormatMask())->class('font-mono text-lg font-bold'),
                    _Html(__('finance-example') . ':')->class('text-gray-600 ml-4'),
                    _Html(AccountSegmentService::getAccountFormatExample())->class('font-mono text-lg'),
                )->class('space-x-2')
            )->class('p-4 bg-gray-50')
        )->class('space-y-4');
    }

    /**
     * Get segment structure form modal
     */
    public function getSegmentStructureFormModal($segmentId = null)
    {
        return new SegmentStructureFormModal($segmentId);
    }
}
