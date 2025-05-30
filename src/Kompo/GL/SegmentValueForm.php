<?php

namespace Condoedge\Finance\Kompo\GL;

use Kompo\Form;
use Condoedge\Finance\Models\GL\GlSegmentValue;
use Condoedge\Finance\Models\GL\AccountSegmentDefinition;

class SegmentValueForm extends Form
{
    public $model = GlSegmentValue::class;
    protected $segmentNumber;

    public function created()
    {
        $this->segmentNumber = request('segment_number', 1);
        
        if (!$this->model->exists) {
            $this->model->segment_number = $this->segmentNumber;
            $this->model->segment_type = GlSegmentValue::TYPE_SEGMENT_VALUE;
        }
    }

    public function render()
    {
        $definition = AccountSegmentDefinition::getByPosition($this->segmentNumber);
        
        return [
            _Title($this->model->exists ? 'Edit Segment Value' : 'Add Segment Value')
                ->class('text-2xl font-bold mb-6'),
                
            _Html("Segment: {$definition->segment_name} (Position {$this->segmentNumber})")
                ->class('text-gray-600 mb-4'),
                
            _Columns(
                _Input('segment_value')
                    ->label('Segment Value')
                    ->placeholder("Enter {$definition->segment_length} character code")
                    ->maxlength($definition->segment_length)
                    ->minlength($definition->segment_length)
                    ->required()
                    ->class('font-mono'),
                    
                _Input('segment_description')
                    ->label('Description')
                    ->placeholder('Description for this segment value')
                    ->required()
            ),
            
            _Checkbox('is_active')
                ->label('Active')
                ->default(true),
                
            _FlexEnd(
                _Link('Cancel')
                    ->href(url()->previous())
                    ->class('btn btn-outline-secondary mr-2'),
                    
                _SubmitButton($this->model->exists ? 'Update' : 'Create')
                    ->class('btn-primary')
            )->class('mt-6')
        ];
    }

    public function rules()
    {
        $definition = AccountSegmentDefinition::getByPosition($this->segmentNumber);
        
        return [
            'segment_value' => [
                'required',
                'string',
                "min:{$definition->segment_length}",
                "max:{$definition->segment_length}",
                'unique:fin_gl_segment_values,segment_value,NULL,segment_value_id,segment_number,' . $this->segmentNumber
            ],
            'segment_description' => 'required|string|max:255',
            'is_active' => 'boolean'
        ];
    }

    public function authorize()
    {
        return true; // Add proper authorization
    }
}
