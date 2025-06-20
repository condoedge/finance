<?php

namespace Condoedge\Finance\Kompo\ChartOfAccounts;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Facades\AccountSegmentService;
use Kompo\Form;

class AccountFormModal extends Form
{
    public $model = GlAccount::class;
    
    protected $segmentValues = [];
    protected $isEditMode = false;
    
    public function created()
    {
        if ($this->model->id) {
            $this->isEditMode = true;
            // Parse existing account segments
            $this->segmentValues = AccountSegmentService::parseAccountId($this->model->account_id);
        }
    }
    
    public function render()
    {
        $segmentDefinitions = AccountSegment::getAllOrdered();
        
        return _Modal(
            _ModalHeader(
                _Title($this->isEditMode ? __('finance-edit-account') : __('finance-create-account')),
                _SubmitButton('general.save')
            ),
            
            _ModalBody(
                // Segment selection
                _Card(
                    _TitleMini('finance-account-segments')->class('mb-4'),
                    _Rows(
                        $segmentDefinitions->map(function ($definition, $index) {
                            $options = AccountSegmentService::getSegmentValueOptions($definition->segment_position);
                            $currentValue = $this->segmentValues[$index] ?? null;
                            
                            return _Select($definition->segment_description)
                                ->name("segments[{$definition->segment_position}]")
                                ->options($options)
                                ->value($currentValue)
                                ->required()
                                ->onChange->selfPost('updateAccountPreview');
                        })
                    )->class('space-y-3'),
                    
                    // Account ID preview
                    _Rows(
                        _Html('finance-account-code-preview')->class('text-sm text-gray-500'),
                        _Html()->id('account-id-preview')
                            ->class('font-mono text-lg font-bold')
                    )->class('mt-4 p-3 bg-gray-50 rounded')
                )->class('mb-4'),
                
                // Account details
                _Card(
                    _TitleMini('finance-account-details')->class('mb-4'),
                    
                    _Input('finance-account-description')
                        ->name('account_description')
                        ->placeholder('finance-enter-account-description')
                        ->maxlength(255),
                    
                    _Select('finance-account-type')
                        ->name('account_type')
                        ->options([
                            'asset' => __('finance-asset'),
                            'liability' => __('finance-liability'),
                            'equity' => __('finance-equity'),
                            'revenue' => __('finance-revenue'),
                            'expense' => __('finance-expense'),
                        ])
                        ->required(),
                    
                    _Columns(
                        _Checkbox('finance-account-active')
                            ->name('is_active')
                            ->value(1)
                            ->default($this->isEditMode ? $this->model->is_active : true),
                            
                        _Checkbox('finance-allow-manual-entry')
                            ->name('allow_manual_entry')
                            ->value(1)
                            ->default($this->isEditMode ? $this->model->allow_manual_entry : true)
                            ->comment('finance-allow-manual-entry-help'),
                    )->class('gap-6')
                ),
                
                // Warning for system accounts
                !$this->isEditMode || $this->model->allow_manual_entry ? null :
                    _Alert('finance-system-account-warning')
                        ->class('mt-4')
                        ->icon('alert-triangle')
                        ->warning(),
            )
        )->class('max-w-2xl');
    }
    
    public function updateAccountPreview()
    {
        $segments = request('segments', []);
        
        if (empty(array_filter($segments))) {
            return _Html('---')->id('account-id-preview');
        }
        
        // Build account ID from selected segments
        $accountId = AccountSegmentService::buildAccountId($segments);
        
        // Check if account already exists
        $exists = GlAccount::where('account_id', $accountId)
            ->where('team_id', currentTeamId())
            ->when($this->isEditMode, fn($q) => $q->where('id', '!=', $this->model->id))
            ->exists();
        
        if ($exists) {
            return _Html($accountId . ' ' . __('finance-account-already-exists'))
                ->id('account-id-preview')
                ->class('text-danger');
        }
        
        return _Html($accountId)->id('account-id-preview')->class('text-success');
    }
    
    public function beforeSave()
    {
        $segments = request('segments', []);
        
        if (empty(array_filter($segments))) {
            throw new \Exception(__('finance-please-select-all-segments'));
        }
        
        // For new accounts, create using segments
        if (!$this->isEditMode) {
            $this->model = AccountSegmentService::createAccount($segments, [
                'account_description' => request('account_description'),
                'account_type' => request('account_type'),
                'is_active' => request('is_active', false),
                'allow_manual_entry' => request('allow_manual_entry', false),
                'team_id' => currentTeamId(),
            ]);
            
            // Prevent the default save
            $this->preventSave = true;
        } else {
            // For existing accounts, only update the allowed fields
            $this->model->fill([
                'account_description' => request('account_description'),
                'account_type' => request('account_type'),
                'is_active' => request('is_active', false),
                'allow_manual_entry' => request('allow_manual_entry', false),
            ]);
        }
    }
    
    public function rules()
    {
        return [
            'segments.*' => 'required',
            'account_type' => 'required|in:asset,liability,equity,revenue,expense',
        ];
    }
}
