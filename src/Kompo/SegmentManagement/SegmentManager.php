<?php

namespace Condoedge\Finance\Kompo\SegmentManagement;

use Condoedge\Utils\Kompo\Common\Form;

class SegmentManager extends Form
{
    public $id = 'finance-segment-manager';

    public $class = 'max-w-6xl mx-auto';
    
    protected $activeTab = 'structure';
    protected $selectedSegmentPosition;
    
    public function created()
    {
        $this->activeTab = $this->prop('tab') ?: 'structure';
        $this->selectedSegmentPosition = $this->prop('position');
    }
    
    public function render()
    {
        return [
            _TitleMain('finance-segment-management')->class('mb-6'),
            
            // Navigation tabs
            _Flex(
                _TabLink(__('finance-segment-structure'), $this->activeTab === 'structure')
                    ->href('finance.segment-manager', ['tab' => 'structure']),
                _TabLink(__('finance-segment-values'), $this->activeTab === 'values')
                    ->href('finance.segment-manager', ['tab' => 'values']),
                _TabLink(__('finance-segment-validation'), $this->activeTab === 'validation')
                    ->href('finance.segment-manager', ['tab' => 'validation']),
            )->class('mb-6 border-b'),
            
            // Content based on active tab. Lazy load instead of using _Tabs component
            match($this->activeTab) {
                'structure' => $this->renderStructureTab(),
                'values' => $this->renderValuesTab(),
                'validation' => $this->renderValidationTab(),
                default => null,
            },
        ];
    }
    
    /**
     * Render the segment structure management tab
     */
    protected function renderStructureTab()
    {
        return new SegmentStructurePage();
    }
    
    /**
     * Render the segment values management tab
     */
    protected function renderValuesTab()
    {
        return new SegmentsValuesPage();
    }
    
    /**
     * Render validation tab
     */
    protected function renderValidationTab()
    {
        return new SegmentValidationPage();
    }
}
