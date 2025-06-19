<?php

namespace Condoedge\Finance\Kompo\SegmentManagement;

use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Models\SegmentValue;
use Condoedge\Finance\Facades\AccountSegmentService;
use Kompo\Form;

class SegmentManager extends Form
{
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
            
            // Content based on active tab
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
        $segments = AccountSegment::getAllOrdered();
        
        return _Rows(
            _Card(
                _FlexBetween(
                    _TitleMini('finance-current-segment-structure'),
                    _Button('finance-add-segment')->icon('plus')
                        ->selfGet('getSegmentStructureFormModal')->inModal()
                        ->disabled($segments->count() >= 10) // Reasonable limit
                )->class('mb-4'),
                
                $segments->isEmpty() ? 
                    _Html('finance-no-segments-defined')->class('text-center text-gray-500 py-8') :
                    _Table(
                        _TableHead(
                            _Th('finance-position'),
                            _Th('finance-description'),
                            _Th('finance-length'),
                            _Th('finance-format-example'),
                            _Th('finance-actions')->class('text-right'),
                        ),
                        _TableBody(
                            $segments->map(fn($segment) => _TableRow(
                                _Td($segment->segment_position),
                                _Td($segment->segment_description),
                                _Td($segment->segment_length . ' ' . __('finance-characters')),
                                _Td(str_repeat('X', $segment->segment_length))->class('font-mono'),
                                _Td(
                                    _FlexEnd(
                                        _Link()->icon('pencil')
                                            ->selfGet('getSegmentStructureFormModal', [
                                                'segment_id' => $segment->id
                                            ])->inModal(),
                                        _Link()->icon('trash')
                                            ->selfPost('deleteSegmentStructure', [
                                                'segment_id' => $segment->id
                                            ])
                                            ->confirm('finance-confirm-delete-segment-structure')
                                            ->disabled($segment->hasValues())
                                    )->class('space-x-2')
                                )->class('text-right')
                            ))
                        )
                    )
            ),
            
            // Current account format display
            _Card(
                _TitleMini('finance-account-format')->class('mb-2'),
                _Flex(
                    _Html(__('finance-current-format') . ':')->class('text-gray-600'),
                    _Html(AccountSegmentService::getAccountFormatMask())->class('font-mono text-lg font-bold'),
                    _Html(__('finance-example') . ':')->class('text-gray-600 ml-4'),
                    _Html($this->generateExampleAccountId())->class('font-mono text-lg'),
                )->class('space-x-2')
            )->class('p-4 bg-gray-50')
        )->class('space-y-4');
    }
    
    /**
     * Render the segment values management tab
     */
    protected function renderValuesTab()
    {
        $segments = AccountSegment::getAllOrdered();
        
        if ($segments->isEmpty()) {
            return _Alert('finance-define-segment-structure-first')
                ->warning()
                ->action(
                    _Link('finance-go-to-structure')
                        ->href('finance.segment-manager', ['tab' => 'structure'])
                );
        }
        
        // Segment position selector
        $segmentSelector = _Card(
            _Select('finance-select-segment-to-manage')
                ->options($segments->pluck('segment_description', 'segment_position'))
                ->value($this->selectedSegmentPosition)
                ->onChange->selfGet('loadSegmentValues')->inPanel('segment-values-panel')
        )->class('mb-4');
        
        return _Rows(
            $segmentSelector,
            _Panel(
                $this->selectedSegmentPosition ? 
                    $this->renderSegmentValuesTable($this->selectedSegmentPosition) :
                    _Html('finance-select-segment-to-view-values')->class('text-center text-gray-500 py-8')
            )->id('segment-values-panel')
        );
    }
    
    /**
     * Render segment values table for a specific position
     */
    protected function renderSegmentValuesTable($position)
    {
        $segment = AccountSegment::getByPosition($position);
        $values = SegmentValue::getForPosition($position, false); // Include inactive
        
        return _Card(
            _FlexBetween(
                _TitleMini(sprintf(__('finance-values-for-segment'), $segment->segment_description)),
                _Button('finance-add-value')->icon('plus')
                    ->selfGet('getSegmentValueFormModal', [
                        'position' => $position
                    ])->inModal()
            )->class('mb-4'),
            
            $values->isEmpty() ?
                _Html('finance-no-values-defined')->class('text-center text-gray-500 py-8') :
                _Table(
                    _TableHead(
                        _Th('finance-value'),
                        _Th('finance-description'),
                        _Th('finance-status'),
                        _Th('finance-usage'),
                        _Th('finance-actions')->class('text-right'),
                    ),
                    _TableBody(
                        $values->map(fn($value) => _TableRow(
                            _Td($value->segment_value)->class('font-mono font-bold'),
                            _Td($value->segment_description),
                            _Td(
                                $value->is_active ?
                                    _Pill(__('finance-active'))->class('bg-success text-white') :
                                    _Pill(__('finance-inactive'))->class('bg-gray-200 text-gray-600')
                            ),
                            _Td($value->getUsageCount() . ' ' . __('finance-accounts')),
                            _Td(
                                _FlexEnd(
                                    _Link()->icon('pencil')
                                        ->selfGet('getSegmentValueFormModal', [
                                            'value_id' => $value->id
                                        ])->inModal(),
                                    _Link()->icon($value->is_active ? 'ban' : 'check')
                                        ->selfPost('toggleSegmentValueStatus', [
                                            'value_id' => $value->id
                                        ])
                                        ->tooltip($value->is_active ? 
                                            __('finance-deactivate') : 
                                            __('finance-activate')
                                        ),
                                    _Link()->icon('trash')
                                        ->selfPost('deleteSegmentValue', [
                                            'value_id' => $value->id
                                        ])
                                        ->confirm('finance-confirm-delete-segment-value')
                                        ->disabled(!$value->canBeDeleted())
                                )->class('space-x-2')
                            )->class('text-right')
                        ))
                    )
                ),
            
            // Bulk import section
            _Card(
                _TitleMini('finance-bulk-import-values')->class('mb-2'),
                _Html('finance-bulk-import-help')->class('text-sm text-gray-600 mb-3'),
                _Textarea('finance-bulk-values')
                    ->name('bulk_values')
                    ->placeholder("10|Parent Team 10\n20|Parent Team 20\n30|Parent Team 30")
                    ->rows(5),
                _Button('finance-import-values')
                    ->selfPost('importBulkValues', ['position' => $position])
                    ->inPanel('segment-values-panel')
            )->class('mt-4 p-4 bg-gray-50')
        );
    }
    
    /**
     * Render validation tab
     */
    protected function renderValidationTab()
    {
        $issues = AccountSegmentService::validateSegmentStructure();
        $stats = $this->getSegmentStatistics();
        
        return _Rows(
            // Validation results
            _Card(
                _TitleMini('finance-structure-validation')->class('mb-4'),
                $issues ? 
                    _Rows(
                        $issues->map(fn($issue) => _Alert($issue)->warning())
                    )->class('space-y-2') :
                    _Alert('finance-segment-structure-valid')->success()
            ),
            
            // Statistics
            _Card(
                _TitleMini('finance-segment-statistics')->class('mb-4'),
                _Columns(
                    _Rows(
                        _Html(__('finance-total-segments'))->class('text-sm text-gray-500'),
                        _Html($stats['total_segments'])->class('text-2xl font-bold')
                    ),
                    _Rows(
                        _Html(__('finance-total-values'))->class('text-sm text-gray-500'),
                        _Html($stats['total_values'])->class('text-2xl font-bold')
                    ),
                    _Rows(
                        _Html(__('finance-active-values'))->class('text-sm text-gray-500'),
                        _Html($stats['active_values'])->class('text-2xl font-bold text-success')
                    ),
                    _Rows(
                        _Html(__('finance-total-accounts'))->class('text-sm text-gray-500'),
                        _Html($stats['total_accounts'])->class('text-2xl font-bold')
                    )
                )->class('gap-8')
            )->class('p-4 bg-gray-50'),
            
            // Coverage analysis
            _Card(
                _TitleMini('finance-segment-coverage')->class('mb-4'),
                _Table(
                    _TableHead(
                        _Th('finance-segment'),
                        _Th('finance-total-values'),
                        _Th('finance-active-values'),
                        _Th('finance-usage-percentage'),
                    ),
                    _TableBody(
                        AccountSegment::getAllOrdered()->map(function($segment) {
                            $totalValues = $segment->segmentValues()->count();
                            $activeValues = $segment->activeSegmentValues()->count();
                            $usedValues = $segment->segmentValues()
                                ->whereHas('accountAssignments')
                                ->count();
                            $usagePercentage = $totalValues > 0 ? 
                                round(($usedValues / $totalValues) * 100, 1) : 0;
                            
                            return _TableRow(
                                _Td($segment->segment_description),
                                _Td($totalValues),
                                _Td($activeValues),
                                _Td(
                                    _Progress($usagePercentage)
                                        ->class('w-full')
                                        ->append(_Html($usagePercentage . '%')->class('ml-2'))
                                )
                            );
                        })
                    )
                )
            )
        )->class('space-y-4');
    }
    
    /**
     * Load segment values for a specific position
     */
    public function loadSegmentValues()
    {
        $this->selectedSegmentPosition = request('value');
        return $this->renderSegmentValuesTable($this->selectedSegmentPosition);
    }
    
    /**
     * Get segment value form modal
     */
    public function getSegmentValueFormModal($position = null, $valueId = null)
    {
        return new SegmentValueFormModal($position, $valueId);
    }
    
    /**
     * Get segment structure form modal
     */
    public function getSegmentStructureFormModal($segmentId = null)
    {
        return new SegmentStructureFormModal($segmentId);
    }
    
    /**
     * Toggle segment value active status
     */
    public function toggleSegmentValueStatus($valueId)
    {
        $value = SegmentValue::findOrFail($valueId);
        $value->update(['is_active' => !$value->is_active]);
        
        return $this->renderSegmentValuesTable($value->segmentDefinition->segment_position);
    }
    
    /**
     * Delete segment value
     */
    public function deleteSegmentValue($valueId)
    {
        $value = SegmentValue::findOrFail($valueId);
        
        if (!$value->canBeDeleted()) {
            return _Alert('finance-cannot-delete-value-in-use')->error();
        }
        
        $position = $value->segmentDefinition->segment_position;
        $value->delete();
        
        return $this->renderSegmentValuesTable($position);
    }
    
    /**
     * Delete segment structure
     */
    public function deleteSegmentStructure($segmentId)
    {
        $segment = AccountSegment::findOrFail($segmentId);
        
        if ($segment->hasValues()) {
            return _Alert('finance-cannot-delete-segment-with-values')->error();
        }
        
        $segment->delete();
        
        // Reorder remaining segments
        AccountSegment::reorderPositions();
        
        return redirect()->route('finance.segment-manager', ['tab' => 'structure']);
    }
    
    /**
     * Import bulk values
     */
    public function importBulkValues($position)
    {
        $bulkData = request('bulk_values');
        
        if (empty($bulkData)) {
            return _Alert('finance-no-data-to-import')->error();
        }
        
        $lines = explode("\n", trim($bulkData));
        $imported = 0;
        $errors = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $parts = explode('|', $line);
            if (count($parts) !== 2) {
                $errors[] = sprintf(__('finance-invalid-format-line'), $line);
                continue;
            }
            
            [$value, $description] = array_map('trim', $parts);
            
            try {
                AccountSegmentService::createSegmentValue($position, $value, $description);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = sprintf(__('finance-error-importing-value'), $value, $e->getMessage());
            }
        }
        
        $alerts = [];
        
        if ($imported > 0) {
            $alerts[] = _Alert(sprintf(__('finance-values-imported-successfully'), $imported))->success();
        }
        
        if (!empty($errors)) {
            $alerts[] = _Alert(implode('<br>', $errors))->error();
        }
        
        return _Rows($alerts)->append($this->renderSegmentValuesTable($position));
    }
    
    /**
     * Generate example account ID based on current structure
     */
    protected function generateExampleAccountId()
    {
        $segments = AccountSegment::getAllOrdered();
        $examples = [];
        
        foreach ($segments as $segment) {
            $value = $segment->activeSegmentValues()->first();
            $examples[] = $value ? $value->segment_value : str_repeat('0', $segment->segment_length);
        }
        
        return implode('-', $examples);
    }
    
    /**
     * Get segment statistics
     */
    protected function getSegmentStatistics()
    {
        return [
            'total_segments' => AccountSegment::count(),
            'total_values' => SegmentValue::count(),
            'active_values' => SegmentValue::where('is_active', true)->count(),
            'total_accounts' => \Condoedge\Finance\Models\GlAccount::forTeam()->count(),
        ];
    }
}
