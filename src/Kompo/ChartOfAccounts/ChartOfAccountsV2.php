<?php

namespace Condoedge\Finance\Kompo\ChartOfAccounts;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Models\SegmentValue;
use Condoedge\Finance\Facades\AccountSegmentService;
use Kompo\Form;

class ChartOfAccountsV2 extends Form
{
    public $class = 'max-w-6xl mx-auto';

    protected $accountType;
    protected $showInactive = false;
    protected $searchTerm;
    protected $selectedSegmentValues = [];
    
    protected $coaPanelId = 'chart-accounts-panel';
    protected $filterPanelId = 'segment-filter-panel';
    
    protected $segmentStructure;
    protected $lastSegmentDefinition;

    public function created()
    {
        $this->accountType = $this->prop('account_type') ?: 'all';
        $this->showInactive = $this->prop('show_inactive') ?: false;
        $this->searchTerm = $this->prop('search');
        $this->selectedSegmentValues = $this->prop('segment_values') ?: [];
        
        // Load segment structure
        $this->segmentStructure = AccountSegmentService::getSegmentStructure();
        $this->lastSegmentDefinition = $this->segmentStructure->last();
    }

    public function render()
    {
        return [
            // Header section
            _FlexBetween(
                _TitleMain('finance-chart-of-accounts')->class('mb-4'),
                _FlexEnd(
                    // Toggle active/inactive accounts
                    _Link(
                        '<span class="hidden sm:inline">'.
                            ($this->showInactive ? __('finance-show-active-accounts') : __('finance-show-all-accounts')).
                        '</span>'
                    )->icon($this->showInactive ? 'eye' : 'eye-off')
                    ->class('text-sm')
                    ->href('finance.chart-of-accounts-v2', [
                        'account_type' => $this->accountType,
                        'show_inactive' => !$this->showInactive,
                        'segment_values' => $this->selectedSegmentValues,
                    ]),
                    
                    // Add new account button
                    _Button('finance-add-account')->outlined()
                        ->icon('plus')
                        ->selfGet('getAccountFormModal')->inModal(),
                        
                    // Segment structure management
                    _Link('finance-manage-segments')->button()->outlined()
                        ->icon('settings')
                        ->href('finance.segment-manager'),
                )->class('space-x-4 mb-4')
            )->class('flex-wrap mb-6'),
            
            // Current segment structure info
            $this->renderSegmentStructureInfo(),
            
            // Filter section
            _Card(
                _Columns(
                    // Search by account code or description
                    _Input('finance-search-accounts')
                        ->placeholder('finance-search-by-code-or-description')
                        ->value($this->searchTerm)
                        ->name('search')
                        ->icon('search')
                        ->onEnter->selfGet('filterAccounts')->inPanel($this->coaPanelId),
                        
                    // Filter by segments
                    _Button('finance-filter-by-segments')
                        ->icon('filter')
                        ->outlined()
                        ->selfGet('getSegmentFilterPanel')->inPanel($this->filterPanelId),
                        
                    // Clear filters
                    $this->hasActiveFilters() ? 
                        _Link('finance-clear-filters')
                            ->icon('x')
                            ->class('text-sm text-danger')
                            ->href('finance.chart-of-accounts-v2') : null,
                )->class('gap-4')
            )->class('mb-4 p-4'),
            
            // Segment filter panel
            _Panel()->id($this->filterPanelId),
            
            // Account type tabs
            $this->renderAccountTypeTabs(),
            
            // Accounts display panel
            _Panel(
                $this->renderAccountsList()
            )->id($this->coaPanelId),
            
            // Summary statistics
            $this->renderAccountStatistics(),
        ];
    }
    
    /**
     * Render segment structure information
     */
    protected function renderSegmentStructureInfo()
    {
        if ($this->segmentStructure->isEmpty()) {
            return _Alert('finance-no-segment-structure-defined')
                ->icon('alert-triangle')
                ->class('mb-4');
        }
        
        $formatExample = AccountSegmentService::getAccountFormatExample();
        
        return _Card(
            _Flex(
                _Html(__('finance-account-format'))->class('text-sm text-gray-600'),
                _Html($formatExample)->class('font-mono font-bold'),
                _Html('|')->class('text-gray-300 mx-2'),
                collect($this->segmentStructure)->map(fn($seg) => 
                    _Html("{$seg->segment_description} ({$seg->segment_length})")
                        ->class('text-xs text-gray-500')
                )->implode(' - ')
            )->class('items-center space-x-2')
        )->class('mb-4 p-3 bg-blue-50 border-blue-200');
    }
    
    /**
     * Render account type filter tabs
     */
    protected function renderAccountTypeTabs()
    {
        $accountTypes = [
            'all' => __('finance-all-accounts'),
            'assets' => __('finance-assets'),
            'liabilities' => __('finance-liabilities'),
            'equity' => __('finance-equity'),
            'revenue' => __('finance-revenue'),
            'expenses' => __('finance-expenses'),
        ];
        
        return _Flex(
            collect($accountTypes)->map(
                fn($label, $type) => _TabLink($label, $this->accountType === $type)
                    ->href('finance.chart-of-accounts-v2', [
                        'account_type' => $type,
                        'show_inactive' => $this->showInactive,
                        'segment_values' => $this->selectedSegmentValues,
                        'search' => $this->searchTerm,
                    ])
            )
        )->class('mb-4 border-b');
    }
    
    /**
     * Render the accounts list grouped by last segment
     */
    protected function renderAccountsList()
    {
        $query = $this->buildAccountsQuery();
        
        if (!$this->lastSegmentDefinition) {
            return _Html('finance-segment-structure-not-configured')->class('text-center text-gray-500 py-8');
        }
        
        // Group accounts by their last segment value
        $accounts = $query->get();
        $accountsGrouped = $accounts->groupBy(function ($account) {
            return $account->lastSegmentValue ? $account->lastSegmentValue->segment_value : 'UNKNOWN';
        });
        
        if ($accountsGrouped->isEmpty()) {
            return _Html('finance-no-accounts-found')->class('text-center text-gray-500 py-8');
        }
        
        return _Rows(
            $accountsGrouped->map(function ($accounts, $lastSegmentCode) {
                $firstAccount = $accounts->first();
                $lastSegmentValue = $firstAccount->lastSegmentValue;
                
                return _Card(
                    _FlexBetween(
                        _Rows(
                            // Last segment header
                            _FlexBetween(
                                _Flex(
                                    _Html($lastSegmentCode)->class('font-mono font-bold text-lg'),
                                    _Html($lastSegmentValue ? $lastSegmentValue->segment_description : '')
                                        ->class('text-gray-600'),
                                )->class('space-x-3'),
                                _Flex(
                                    _Html(sprintf(__('finance-n-accounts'), $accounts->count()))
                                        ->class('text-sm text-gray-500'),
                                    _Html($this->lastSegmentDefinition->segment_description)
                                        ->class('text-xs text-gray-400 ml-2')
                                )->class('items-center')
                            )->class('mb-2'),
                            
                            // List of full accounts under this last segment
                            _Rows(
                                $accounts->map(fn($account) => $this->renderAccountRow($account))
                            )->class('space-y-1 ml-4')
                        ),
                        
                        // Actions
                        _FlexEnd(
                            _Button()->icon('chevron-down')
                                ->class('text-gray-400')
                                ->toggleId('segment-group-'.$lastSegmentCode)
                        )->class('ml-4')
                    )->class('cursor-pointer')
                    ->id('segment-header-'.$lastSegmentCode)
                    ->onclick('toggleSegmentGroup("'.$lastSegmentCode.'")')
                )->class('p-4 mb-2 hover:shadow-md transition-shadow')
                ->id('segment-group-'.$lastSegmentCode);
            })
        )->class('space-y-2');
    }
    
    /**
     * Render individual account row
     */
    protected function renderAccountRow($account)
    {
        return _FlexBetween(
            _Flex(
                // Account code
                _Html($account->account_id)->class('font-mono font-semibold'),
                
                // Account description with segment breakdown
                _Rows(
                    _Html($account->account_description ?: __('finance-no-description'))
                        ->class($account->is_active ? '' : 'text-gray-400'),
                    // Show segment descriptor from database
                    _Html($account->account_segments_descriptor)
                        ->class('text-xs text-gray-500')
                ),
                
                // Status indicators
                _Flex(
                    !$account->is_active ? 
                        _Pill(__('finance-inactive'))->class('bg-gray-200 text-gray-600 text-xs') : null,
                    !$account->allow_manual_entry ? 
                        _Pill(__('finance-system-only'))->class('bg-yellow-100 text-yellow-700 text-xs') : null,
                )->class('space-x-2')
            )->class('space-x-4 items-start'),
            
            // Actions
            _FlexEnd(
                // Edit last segment only
                _Link()->icon('pencil')
                    ->class('text-gray-400 hover:text-primary')
                    ->tooltip(__('finance-edit-last-segment'))
                    ->selfGet('getEditLastSegmentModal', ['account_id' => $account->id])->inModal(),
                    
                // View transactions
                _Link()->icon('eye')
                    ->class('text-gray-400 hover:text-primary')
                    ->tooltip(__('finance-view-transactions'))
                    ->href('finance.account-transactions', ['account_id' => $account->id]),
                    
                // Full edit (admin only)
                auth()->user()->can('finance.accounts.full-edit') ?
                    _Link()->icon('cog')
                        ->class('text-gray-400 hover:text-warning')
                        ->tooltip(__('finance-full-edit'))
                        ->selfGet('getAccountFormModal', ['account_id' => $account->id])->inModal() : null,
            )->class('space-x-2')
        )->class('py-2 px-3 hover:bg-gray-50 rounded');
    }
    
    /**
     * Build query for accounts based on filters
     */
    protected function buildAccountsQuery()
    {
        $query = GlAccount::forTeam()
            ->with(['segmentValues.segmentDefinition']);
        
        // Filter by active status
        if (!$this->showInactive) {
            $query->active();
        }
        
        // Filter by account type
        if ($this->accountType !== 'all') {
            $query->whereAccountType($this->accountType);
        }
        
        // Search filter
        if ($this->searchTerm) {
            $query->where(function ($q) {
                $q->where('account_id', 'like', '%' . $this->searchTerm . '%')
                  ->orWhere('account_description', 'like', '%' . $this->searchTerm . '%')
                  ->orWhere('account_segments_descriptor', 'like', '%' . $this->searchTerm . '%');
            });
        }
        
        // Segment value filters
        if (!empty($this->selectedSegmentValues)) {
            foreach ($this->selectedSegmentValues as $segmentValueId) {
                if ($segmentValueId) {
                    $query->withSegmentValue($segmentValueId);
                }
            }
        }
        
        return $query->orderBy('account_id');
    }
    
    /**
     * Get segment filter panel
     */
    public function getSegmentFilterPanel()
    {
        $segmentGroups = AccountSegmentService::getSegmentValuesGrouped();
        
        return _Card(
            _TitleMini('finance-filter-by-segments')->class('mb-4'),
            _Rows(
                $segmentGroups->map(function ($group, $segmentId) {
                    $definition = $group['definition'];
                    $values = $group['values'];
                    
                    $options = $values->mapWithKeys(fn($v) => [
                        $v->id => "{$v->segment_value} - {$v->segment_description}"
                    ]);
                    
                    return _Select($definition->segment_description)
                        ->name("segment_values[]")
                        ->options($options->prepend(__('finance-all'), ''))
                        ->value(
                            collect($this->selectedSegmentValues)
                                ->filter(fn($id) => $values->pluck('id')->contains($id))
                                ->first()
                        );
                })
            )->class('space-y-3'),
            _FlexEnd(
                _Button('finance-apply-filters')
                    ->submit()
                    ->inPanel($this->coaPanelId),
                _Link('finance-cancel')->class('text-gray-500')
                    ->emitDirect('closePanel', ['panelId' => $this->filterPanelId])
            )->class('space-x-3 mt-4')
        )->class('p-4');
    }
    
    /**
     * Get modal to edit last segment only
     */
    public function getEditLastSegmentModal($accountId)
    {
        $account = GlAccount::with(['segmentValues.segmentDefinition'])->findOrFail($accountId);
        
        if (!$this->lastSegmentDefinition) {
            return _Modal(
                _Alert('finance-no-segment-structure')->danger()
            );
        }
        
        $lastSegmentValue = $account->lastSegmentValue;
        $availableValues = AccountSegmentService::getSegmentValues($this->lastSegmentDefinition->id);
        
        return _Modal(
            _ModalHeader(__('finance-edit-account-segment')),
            _Card(
                _Rows(
                    // Show account info
                    _Html(__('finance-account'))->class('text-sm text-gray-600'),
                    _Html($account->account_id)->class('font-mono font-bold mb-2'),
                    _Html($account->account_segments_descriptor)->class('text-sm text-gray-500 mb-4'),
                    
                    // Edit last segment
                    _Select($this->lastSegmentDefinition->segment_description)
                        ->name('new_segment_value_id')
                        ->options(
                            $availableValues->mapWithKeys(fn($v) => [
                                $v->id => "{$v->segment_value} - {$v->segment_description}"
                            ])
                        )
                        ->value($lastSegmentValue ? $lastSegmentValue->id : null)
                        ->required(),
                        
                    _Html(__('finance-edit-last-segment-note'))
                        ->class('text-xs text-gray-500 mt-2')
                )
            )->class('p-4'),
            _FlexEnd(
                _SubmitButton('finance-save-changes')
                    ->selfPost('updateAccountLastSegment', ['account_id' => $accountId])
                    ->refresh(),
                _Link('finance-cancel')->class('ml-3')
                    ->emitDirect('closeModal')
            )->class('p-4 border-t')
        );
    }
    
    /**
     * Update account's last segment
     */
    public function updateAccountLastSegment($accountId)
    {
        $account = GlAccount::findOrFail($accountId);
        $newSegmentValueId = request('new_segment_value_id');
        
        try {
            $account->updateLastSegmentValue($newSegmentValueId);
            $this->notifySuccess(__('finance-account-updated-successfully'));
        } catch (\Exception $e) {
            $this->notifyError($e->getMessage());
        }
    }
    
    /**
     * Get account form modal (full edit)
     */
    public function getAccountFormModal($accountId = null)
    {
        return new AccountFormModal($accountId);
    }
    
    /**
     * Filter accounts based on current criteria
     */
    public function filterAccounts()
    {
        $this->searchTerm = request('search');
        $this->selectedSegmentValues = array_filter(request('segment_values', []));
        
        return $this->renderAccountsList();
    }
    
    /**
     * Check if there are active filters
     */
    protected function hasActiveFilters()
    {
        return $this->searchTerm || !empty($this->selectedSegmentValues);
    }
    
    /**
     * Render account statistics
     */
    protected function renderAccountStatistics()
    {
        $stats = [
            'total' => GlAccount::forTeam()->count(),
            'active' => GlAccount::forTeam()->active()->count(),
            'inactive' => GlAccount::forTeam()->inactive()->count(),
            'system_only' => GlAccount::forTeam()->where('allow_manual_entry', false)->count(),
        ];
        
        return _Card(
            _Columns(
                _Rows(
                    _Html(__('finance-total-accounts'))->class('text-sm text-gray-500'),
                    _Html($stats['total'])->class('text-2xl font-bold')
                ),
                _Rows(
                    _Html(__('finance-active-accounts'))->class('text-sm text-gray-500'),
                    _Html($stats['active'])->class('text-2xl font-bold text-success')
                ),
                _Rows(
                    _Html(__('finance-inactive-accounts'))->class('text-sm text-gray-500'),
                    _Html($stats['inactive'])->class('text-2xl font-bold text-gray-400')
                ),
                _Rows(
                    _Html(__('finance-system-only-accounts'))->class('text-sm text-gray-500'),
                    _Html($stats['system_only'])->class('text-2xl font-bold text-warning')
                ),
            )->class('gap-8')
        )->class('mt-6 p-4 bg-gray-50');
    }
    
    public function js()
    {
        return <<<javascript
function toggleSegmentGroup(code) {
    $('#segment-group-' + code).toggle();
}
javascript;
    }
}
