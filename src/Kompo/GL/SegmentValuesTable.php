<?php

namespace Condoedge\Finance\Kompo\GL;

use Kompo\Table;
use Condoedge\Finance\Models\GL\GlSegmentValue;

class SegmentValuesTable extends Table
{
    protected int $segmentNumber;

    public function __construct(int $segmentNumber)
    {
        $this->segmentNumber = $segmentNumber;
        parent::__construct();
    }

    public function query()
    {
        return GlSegmentValue::getSegmentValues($this->segmentNumber);
    }

    public function headers()
    {
        return [
            _Th('Value'),
            _Th('Description'),
            _Th('Status'),
            _Th('Actions')
        ];
    }

    public function render($segmentValue)
    {
        return [
            _Html($segmentValue->segment_value)->class('font-mono font-semibold'),
            _Html($segmentValue->segment_description),
            _Html($segmentValue->is_active ? 'Active' : 'Inactive')
                ->class($segmentValue->is_active ? 'badge bg-success' : 'badge bg-secondary'),
            
            _FlexEnd(
                _Button('Edit')
                    ->class('btn-sm btn-outline-primary mr-2')
                    ->onClick(fn() => $this->editSegmentValue($segmentValue->segment_value_id)),
                    
                _Button($segmentValue->is_active ? 'Deactivate' : 'Activate')
                    ->class($segmentValue->is_active ? 'btn-sm btn-outline-warning' : 'btn-sm btn-outline-success')
                    ->onClick(fn() => $this->toggleSegmentValue($segmentValue->segment_value_id))
            )
        ];
    }

    public function top()
    {
        return [
            _FlexBetween(
                _Html("Segment {$this->segmentNumber} Values")->class('text-lg font-semibold'),
                
                _Button('Add Value')
                    ->class('btn-primary btn-sm')
                    ->onClick(fn() => $this->addSegmentValue())
            )->class('mb-4')
        ];
    }

    protected function editSegmentValue($segmentValueId)
    {
        return redirect()->to("segment-values/{$segmentValueId}/edit");
    }

    protected function toggleSegmentValue($segmentValueId)
    {
        $segmentValue = GlSegmentValue::findOrFail($segmentValueId);
        $segmentValue->is_active = !$segmentValue->is_active;
        $segmentValue->save();
        
        return $this->refresh();
    }

    protected function addSegmentValue()
    {
        return redirect()->to("segment-values/create?segment_number={$this->segmentNumber}");
    }
}
