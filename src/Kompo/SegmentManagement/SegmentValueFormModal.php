<?php

namespace Condoedge\Finance\Kompo\SegmentManagement;

use Condoedge\Finance\Models\SegmentValue;
use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Facades\AccountSegmentService;
use Kompo\Form;

class SegmentValueFormModal extends Form
{
    public $model = SegmentValue::class;
    
    protected $position;
    protected $isEditMode = false;
    
    public function created()
    {
        $this->position = $this->prop('position');
        
        if ($this->model->id) {
            $this->isEditMode = true;
            $this->position = $this->model->segmentDefinition->segment_position;
        }
    }
    
    public function render()
    {
        $segment = AccountSegment::getByPosition($this->position);
        
        if (!$segment) {
            return _Alert('finance-invalid-segment-position')->error();
        }
        
        return _Modal(
            _ModalHeader(
                _Title($this->isEditMode ? 
                    __('finance-edit-segment-value') : 
                    sprintf(__('finance-add-value-for-segment'), $segment->segment_description)
                ),
                _SubmitButton('general.save')
            ),
            
            _ModalBody(
                _Hidden('segment_definition_id')->value($segment->id),
                
                _Input('finance-segment-value')
                    ->name('segment_value')
                    ->placeholder(sprintf(__('finance-max-n-characters'), $segment->segment_length))
                    ->maxlength($segment->segment_length)
                    ->required()
                    ->disabled($this->isEditMode) // Cannot change value once created
                    ->comment($this->isEditMode ? __('finance-value-cannot-be-changed') : null),
                
                _Input('finance-description')
                    ->name('segment_description')
                    ->placeholder('finance-enter-description')
                    ->maxlength(255)
                    ->required(),
                
                _Checkbox('finance-active')
                    ->name('is_active')
                    ->value(1)
                    ->default($this->isEditMode ? $this->model->is_active : true),
                
                // Show usage information if editing
                $this->isEditMode && $this->model->getUsageCount() > 0 ?
                    _Alert(sprintf(__('finance-value-used-in-n-accounts'), $this->model->getUsageCount()))
                        ->info()
                        ->class('mt-4') : null,
            )
        )->class('max-w-lg');
    }
    
    public function beforeSave()
    {
        // Validate segment value length
        $segment = AccountSegment::find(request('segment_definition_id'));
        $value = request('segment_value');
        
        if (strlen($value) !== $segment->segment_length) {
            throw new \Exception(sprintf(
                __('finance-value-must-be-n-characters'), 
                $segment->segment_length
            ));
        }
        
        // Check for duplicates
        if (!$this->isEditMode) {
            $exists = SegmentValue::where('segment_definition_id', $segment->id)
                ->where('segment_value', $value)
                ->exists();
                
            if ($exists) {
                throw new \Exception(__('finance-segment-value-already-exists'));
            }
        }
    }
    
    public function rules()
    {
        return [
            'segment_value' => 'required|string',
            'segment_description' => 'required|string|max:255',
        ];
    }
}
