<?php

namespace Condoedge\Finance\Kompo\GL;

use Kompo\Form;
use Condoedge\Finance\Models\GL\AccountSegmentDefinition;
use Condoedge\Finance\Models\GL\GlSegmentValue;

class AccountStructureSetupForm extends Form
{
    public function render()
    {
        return [
            _Title('Account Structure Setup')->class('text-2xl font-bold mb-6'),
            
            _Html('Define the structure of your GL account codes using segments.')
                ->class('text-gray-600 mb-6'),
                
            $this->renderCurrentStructure(),
            
            _Title('Add New Segment')->class('text-lg font-semibold mt-8 mb-4'),
            
            _Columns(
                _Input('segment_name')
                    ->label('Segment Name')
                    ->placeholder('e.g., Project, Department, Account')
                    ->required(),
                    
                _Input('segment_length')
                    ->label('Segment Length')
                    ->placeholder('Number of characters')
                    ->type('number')
                    ->min(1)
                    ->max(10)
                    ->required(),
                    
                _Input('segment_description')
                    ->label('Description')
                    ->placeholder('Optional description')
            ),
            
            _SubmitButton('Add Segment')->class('btn-primary mt-4'),
            
            $this->renderSegmentValues()
        ];
    }

    protected function renderCurrentStructure()
    {
        $definitions = AccountSegmentDefinition::getActiveDefinitions();
        
        if ($definitions->isEmpty()) {
            return _Html('No account structure defined yet.')
                ->class('text-gray-500 italic mb-4');
        }

        return [
            _Title('Current Account Structure')->class('text-lg font-semibold mb-4'),
            
            _Html('Account Format: ' . AccountSegmentDefinition::getAccountFormatPattern())
                ->class('font-mono text-lg mb-4 p-3 bg-gray-100 rounded'),
                
            _Table()->headers([
                _Th('Position'),
                _Th('Name'),
                _Th('Length'),
                _Th('Description'),
                _Th('Actions')
            ])->rows(
                $definitions->map(function($def) {
                    return [
                        _Html($def->segment_position),
                        _Html($def->segment_name)->class('font-semibold'),
                        _Html($def->segment_length . ' chars'),
                        _Html($def->segment_description ?: 'No description'),
                        _Button('Edit')
                            ->class('btn-sm btn-outline-primary')
                            ->onClick(fn() => $this->editSegmentDefinition($def->id))
                    ];
                })
            )->class('mb-6')
        ];
    }

    protected function renderSegmentValues()
    {
        $definitions = AccountSegmentDefinition::getActiveDefinitions();
        
        if ($definitions->isEmpty()) {
            return null;
        }

        return [
            _Title('Segment Values')->class('text-lg font-semibold mt-8 mb-4'),
            
            _Tabs(
                $definitions->map(function($def) {
                    return _Tab($def->segment_name)->panel([
                        new SegmentValuesTable($def->segment_position)
                    ]);
                })
            )
        ];
    }

    public function rules()
    {
        return [
            'segment_name' => 'required|string|max:50',
            'segment_length' => 'required|integer|min:1|max:10',
            'segment_description' => 'nullable|string|max:255'
        ];
    }

    public function authorize()
    {
        return true; // Add proper authorization
    }

    protected function editSegmentDefinition($definitionId)
    {
        // Implementation for editing segment definition
        return redirect()->to("account-structure/segments/{$definitionId}/edit");
    }
}
