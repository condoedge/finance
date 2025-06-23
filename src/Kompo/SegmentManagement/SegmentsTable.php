<?php

namespace Condoedge\Finance\Kompo\SegmentManagement;

use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Utils\Kompo\Common\Table;

class SegmentsTable extends Table
{
    public $id = 'segments-table';

    public $orderable = 'segment_position';
    public $dragHandle = '.cursor-move';
    public $browseAfterOrder = true;

    public function query()
    {
        return AccountSegment::getAllOrdered();
    }
    
    public function headers()
    {
        return [
            _Th('#'),
            _Th('finance-position'),
            _Th('finance-description'),
            _Th('finance-length'),
            _Th('finance-format-example'),
            _Th('finance-actions')->class('text-right'),
        ];
    }

    public function render($segment)
    {
        return _TableRow(
            _DragIcon(),
            _Html($segment->segment_position),
            _Html($segment->segment_description),
            _Html($segment->segment_length . ' ' . __('finance-characters')),
            _Html(str_repeat('X', $segment->segment_length))->class('font-mono'),
            _FlexEnd(
                _Link()->icon('pencil')
                    ->selfGet('getSegmentStructureFormModal', [
                        'segment_id' => $segment->id
                    ])->inModal(),
                _Link()->icon('trash')
                    ->selfPost('deleteSegmentStructure', [
                        'segment_id' => $segment->id
                    ]),
            )->class('space-x-2')
        );
    }

    /**
     * Get segment structure form modal
     */
    public function getSegmentStructureFormModal($segmentId = null)
    {
        return new SegmentStructureFormModal($segmentId);
    }
}