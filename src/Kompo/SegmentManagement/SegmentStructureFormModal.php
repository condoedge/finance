<?php

namespace Condoedge\Finance\Kompo\SegmentManagement;

use Condoedge\Finance\Models\AccountSegment;
use Kompo\Form;

class SegmentStructureFormModal extends Form
{
    public $model = AccountSegment::class;
    
    protected $isEditMode = false;
    protected $maxPosition;
    
    public function created()
    {
        if ($this->model->id) {
            $this->isEditMode = true;
        }
        
        // Get the next available position
        $this->maxPosition = AccountSegment::max('segment_position') ?? 0;
    }
    
    public function render()
    {
        return _Modal(
            _ModalHeader(
                _Title($this->isEditMode ? 
                    __('finance-edit-segment-structure') : 
                    __('finance-add-segment-structure')
                ),
                _SubmitButton('general.save')
            ),
            
            _ModalBody(
                _Input('finance-segment-description')
                    ->name('segment_description')
                    ->placeholder('finance-eg-department-project-account')
                    ->maxlength(255)
                    ->required(),
                
                _Input('finance-segment-position')
                    ->name('segment_position')
                    ->type('number')
                    ->min(1)
                    ->max(10)
                    ->required()
                    ->default($this->isEditMode ? $this->model->segment_position : $this->maxPosition + 1)
                    ->disabled($this->isEditMode) // Cannot change position once created
                    ->comment($this->isEditMode ? 
                        __('finance-position-cannot-be-changed') : 
                        __('finance-position-determines-order')
                    ),
                
                _Input('finance-segment-length')
                    ->name('segment_length')
                    ->type('number')
                    ->min(1)
                    ->max(10)
                    ->required()
                    ->default($this->isEditMode ? $this->model->segment_length : 2)
                    ->disabled($this->isEditMode && $this->model->hasValues())
                    ->comment(
                        $this->isEditMode && $this->model->hasValues() ? 
                        __('finance-length-cannot-be-changed-has-values') : 
                        __('finance-number-of-characters-for-segment')
                    ),
                
                // Warning about impact
                _Alert(__('finance-segment-structure-warning'))
                    ->warning()
                    ->class('mt-4')
                    ->icon('alert-triangle'),
                    
                // Show current structure preview
                $this->renderStructurePreview(),
            )
        )->class('max-w-lg');
    }
    
    /**
     * Render structure preview
     */
    protected function renderStructurePreview()
    {
        $segments = AccountSegment::getAllOrdered();
        
        if ($segments->isEmpty() && !$this->isEditMode) {
            return null;
        }
        
        // Build preview including the new/edited segment
        $preview = [];
        $inserted = false;
        
        foreach ($segments as $segment) {
            if ($this->isEditMode && $segment->id === $this->model->id) {
                // Show edited segment
                $preview[] = str_repeat('X', request('segment_length', $segment->segment_length));
            } elseif (!$this->isEditMode && !$inserted && $segment->segment_position > request('segment_position', $this->maxPosition + 1)) {
                // Insert new segment in correct position
                $preview[] = str_repeat('X', request('segment_length', 2));
                $preview[] = str_repeat('X', $segment->segment_length);
                $inserted = true;
            } else {
                $preview[] = str_repeat('X', $segment->segment_length);
            }
        }
        
        // Add new segment at end if not inserted
        if (!$this->isEditMode && !$inserted) {
            $preview[] = str_repeat('X', request('segment_length', 2));
        }
        
        return _Card(
            _TitleMini('finance-account-format-preview')->class('mb-2'),
            _Html(implode('-', $preview))->class('font-mono text-lg text-center')
        )->class('mt-4 p-3 bg-gray-50');
    }
    
    public function beforeSave()
    {
        // Validate position uniqueness
        if (!$this->isEditMode) {
            $position = request('segment_position');
            
            if (AccountSegment::where('segment_position', $position)->exists()) {
                // Need to reorder positions
                AccountSegment::where('segment_position', '>=', $position)
                    ->increment('segment_position');
            }
        }
        
        // Validate that changing length won't break existing values
        if ($this->isEditMode && $this->model->hasValues()) {
            $newLength = request('segment_length');
            if ($newLength != $this->model->segment_length) {
                throw new \Exception(__('finance-cannot-change-length-with-values'));
            }
        }
    }
    
    public function completed()
    {
        // Ensure positions are sequential
        AccountSegment::reorderPositions();
    }
    
    public function rules()
    {
        return [
            'segment_description' => 'required|string|max:255',
            'segment_position' => 'required|integer|min:1|max:10',
            'segment_length' => 'required|integer|min:1|max:10',
        ];
    }
}
