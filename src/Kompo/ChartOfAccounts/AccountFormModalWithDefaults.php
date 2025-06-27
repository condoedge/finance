<?php

namespace Condoedge\Finance\Kompo\ChartOfAccounts;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Dto\Gl\CreateAccountDto;
use Condoedge\Finance\Facades\AccountSegmentService;
use Condoedge\Finance\Enums\SegmentDefaultHandlerEnum;

class AccountFormModalWithDefaults extends \App\Kompo\Common\Modal
{
    public $model = GlAccount::class;
    public $_Title = 'finance-create-account';
    
    protected $segments;
    protected $segmentValues;
    protected $useDefaults = true;
    
    public function created()
    {
        $this->segments = AccountSegmentService::getSegmentStructure();
        $this->segmentValues = AccountSegmentService::getSegmentValuesGrouped();
    }
    
    public function beforeSave()
    {
        // Build segment value IDs array
        $segmentValueIds = [];
        foreach ($this->segments as $segment) {
            $fieldName = "segment_{$segment->id}";
            $segmentValueIds[] = request($fieldName);
        }
        
        // Create DTO
        $dto = new CreateAccountDto([
            'segment_value_ids' => $segmentValueIds,
            'account_description' => request('account_description'),
            'account_type' => request('account_type'),
            'is_active' => request('is_active', true),
            'allow_manual_entry' => request('allow_manual_entry', true),
            'team_id' => currentTeamId(),
            'apply_defaults' => $this->useDefaults,
        ]);
        
        // Create via service
        $account = AccountSegmentService::createAccount($dto);
        $this->model = $account;
        
        // Prevent default save
        return false;
    }
    
    public function afterSave()
    {
        $this->closeModal();
        $this->refreshKomposer();
        notification()->success(__('finance.account-created-successfully'));
    }
    
    public function body()
    {
        return _Rows(
            _Html('finance.account-segments')
                ->class('text-lg font-semibold'),
                
            _Toggle('finance.use-automatic-defaults')
                ->name('use_defaults')
                ->value($this->useDefaults)
                ->emitSelf('toggleDefaults')->withValue()
                ->class('mb-2'),
                
            $this->segmentFields(),
            
            _Html('finance.account-details')
                ->class('text-lg font-semibold mt-4'),
                
            _Input('finance.account-description')
                ->name('account_description')
                ->required(),
                
            _Select('finance.account-type')
                ->name('account_type')
                ->options(\Condoedge\Finance\Models\AccountTypeEnum::optionsWithLabels())
                ->required(),
                
            _Flex(
                _Toggle('finance.active')
                    ->name('is_active')
                    ->value(true),
                    
                _Toggle('finance.allow-manual-entries')
                    ->name('allow_manual_entry')
                    ->value(true)
            )->class('gap-6'),
            
            _FlexEnd(
                _Button('common.cancel')
                    ->closeModal()
                    ->class('mr-2'),
                    
                _SubmitButton('common.save')
                    ->class('btn-primary')
            )->class('mt-6')
        )->class('gap-4');
    }
    
    /**
     * Generate segment input fields
     */
    protected function segmentFields()
    {
        return _Rows(
            ...$this->segments->map(function ($segment) {
                $fieldName = "segment_{$segment->id}";
                $values = $this->segmentValues[$segment->id]['values'] ?? collect();
                
                // Build field label with handler info
                $label = $segment->segment_description;
                if ($segment->hasDefaultHandler() && $this->useDefaults) {
                    $handler = $segment->default_handler_enum;
                    $label .= ' <span class="text-sm text-gray-500">(' . $handler->label() . ')</span>';
                }
                
                // If using defaults and segment has handler, make field optional
                $isRequired = !($this->useDefaults && $segment->hasDefaultHandler());
                
                return _Select($label)
                    ->name($fieldName)
                    ->options(
                        $values->pluck('segment_description', 'id')->prepend(
                            $this->useDefaults && $segment->hasDefaultHandler() 
                                ? __('finance.auto-generate') 
                                : __('finance.select-value'),
                            ''
                        )
                    )
                    ->required($isRequired)
                    ->when(!$isRequired)
                    ->placeholder(__('finance.will-be-auto-generated'))
                    ->emitSelf('segmentChanged', ['segment_id' => $segment->id])->withValue();
            })
        )->class('gap-3');
    }
    
    /**
     * Toggle use of defaults
     */
    public function toggleDefaults($useDefaults)
    {
        $this->useDefaults = $useDefaults;
    }
    
    /**
     * Handle segment value change to show preview
     */
    public function segmentChanged($segmentId, $value)
    {
        // Could implement account ID preview here
    }
    
    /**
     * Get preview of what account ID will be generated
     */
    protected function getAccountPreview(): string
    {
        $parts = [];
        
        foreach ($this->segments as $segment) {
            $fieldName = "segment_{$segment->id}";
            $valueId = request($fieldName);
            
            if ($valueId) {
                $segmentValue = \Condoedge\Finance\Models\SegmentValue::find($valueId);
                $parts[] = $segmentValue ? $segmentValue->segment_value : '??';
            } elseif ($this->useDefaults && $segment->hasDefaultHandler()) {
                // Show placeholder for auto-generated
                $parts[] = str_repeat('?', $segment->segment_length);
            } else {
                $parts[] = str_repeat('?', $segment->segment_length);
            }
        }
        
        return implode('-', $parts);
    }
}
