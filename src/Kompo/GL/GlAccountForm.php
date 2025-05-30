<?php

namespace Condoedge\Finance\Kompo\GL;

use Kompo\Form;
use Condoedge\Finance\Models\GL\GlAccount;
use Condoedge\Finance\Models\GL\AccountSegmentDefinition;
use Condoedge\Finance\Models\GL\GlSegmentValue;

class GlAccountForm extends Form
{
    public $model = GlAccount::class;
    protected $segmentDefinitions;

    public function created()
    {
        $this->segmentDefinitions = AccountSegmentDefinition::getActiveDefinitions();
        
        if ($this->segmentDefinitions->isEmpty()) {
            throw new \Exception('Account structure must be set up before creating accounts');
        }
    }

    public function render()
    {
        return [
            _Title($this->model->exists ? 'Edit GL Account' : 'Create GL Account')
                ->class('text-2xl font-bold mb-6'),
                
            $this->renderAccountStructure(),
            
            _Input('account_description')
                ->label('Account Description')
                ->placeholder('Enter account description')
                ->required(),
                
            _Columns(
                _Select('account_type')
                    ->label('Account Type')
                    ->options([
                        'Asset' => 'Asset',
                        'Liability' => 'Liability',
                        'Equity' => 'Equity',
                        'Revenue' => 'Revenue',
                        'Expense' => 'Expense'
                    ])
                    ->placeholder('Select account type'),
                    
                _Input('account_category')
                    ->label('Account Category')
                    ->placeholder('Optional subcategory')
            ),
            
            _Columns(
                _Checkbox('is_active')
                    ->label('Active')
                    ->default(true),
                    
                _Checkbox('allow_manual_entry')
                    ->label('Allow Manual Entry')
                    ->default(true)
                    ->help('Allow this account to be used in manual journal entries')
            ),
            
            $this->model->exists ? $this->renderAccountInfo() : null,
            
            _FlexEnd(
                _Link('Cancel')
                    ->href(url()->previous())
                    ->class('btn btn-outline-secondary mr-2'),
                    
                _SubmitButton($this->model->exists ? 'Update Account' : 'Create Account')
                    ->class('btn-primary')
            )->class('mt-6')
        ];
    }

    protected function renderAccountStructure()
    {
        $segments = [];
        
        foreach ($this->segmentDefinitions as $definition) {
            $segmentValues = GlSegmentValue::getSegmentValues($definition->segment_position);
            
            $segments[] = _Select("segment{$definition->segment_position}_value")
                ->label("{$definition->segment_name} (Segment {$definition->segment_position})")
                ->options($segmentValues->pluck('segment_description', 'segment_value')->toArray())
                ->placeholder("Select {$definition->segment_name}")
                ->required()
                ->onChange(fn() => $this->updateAccountId());
        }
        
        return [
            _Html('Account Structure')->class('text-lg font-semibold mb-3'),
            
            _Html($this->getAccountPattern())->class('font-mono text-sm text-gray-600 mb-4'),
            
            _Columns(...$segments),
            
            _Html('Generated Account ID: ')
                ->append(_Html($this->generateAccountId())->class('font-mono font-bold text-lg'))
                ->class('mt-4 p-3 bg-gray-50 rounded border')
        ];
    }

    protected function renderAccountInfo()
    {
        if (!$this->model->exists) return null;
        
        return [
            _Title('Account Information')->class('text-lg font-semibold mt-8 mb-4'),
            
            _Columns(
                _Html('Current Balance: ' . number_format($this->model->getBalance(), 2))
                    ->class('text-lg'),
                    
                _Html('Transaction Count: ' . $this->model->glEntries()->count())
                    ->class('text-lg')
            )->class('p-4 bg-blue-50 rounded mb-4'),
            
            _Link('View Transactions')
                ->href("gl-transactions?account_id={$this->model->account_id}")
                ->class('btn btn-outline-primary')
        ];
    }

    protected function getAccountPattern()
    {
        $pattern = [];
        foreach ($this->segmentDefinitions as $definition) {
            $pattern[] = str_repeat('X', $definition->segment_length);
        }
        return 'Pattern: ' . implode('-', $pattern);
    }

    protected function generateAccountId()
    {
        $segments = [];
        foreach ($this->segmentDefinitions as $definition) {
            $value = request("segment{$definition->segment_position}_value", '');
            $segments[] = $value ?: str_repeat('_', $definition->segment_length);
        }
        return implode('-', $segments);
    }

    protected function updateAccountId()
    {
        // This would be handled by JavaScript in a real implementation
        return $this->refresh();
    }

    public function beforeSave()
    {
        // Generate account_id from segments
        $segments = [];
        foreach ($this->segmentDefinitions as $definition) {
            $segments[] = $this->model->{"segment{$definition->segment_position}_value"};
        }
        $this->model->account_id = implode('-', $segments);
    }

    public function rules()
    {
        $rules = [
            'account_description' => 'required|string|max:255',
            'account_type' => 'nullable|in:Asset,Liability,Equity,Revenue,Expense',
            'account_category' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'allow_manual_entry' => 'boolean'
        ];

        // Add rules for each segment
        foreach ($this->segmentDefinitions as $definition) {
            $rules["segment{$definition->segment_position}_value"] = 'required|string';
        }

        return $rules;
    }

    public function authorize()
    {
        return true; // Add proper authorization
    }
}
