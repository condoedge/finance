<?php

namespace Condoedge\Finance\Http\Controllers\Api;

use Condoedge\Finance\Facades\AccountSegmentService;
use Condoedge\Finance\Facades\GlAccountService;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Dto\Gl\CreateAccountDto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends ApiController
{
    /**
     * List accounts with filters
     */
    public function index(Request $request)
    {
        $query = GlAccount::with(['segmentAssignments.segmentValue.segmentDefinition']);
        
        // Apply filters
        if ($request->has('active')) {
            $request->boolean('active') ? $query->active() : $query;
        }
        
        if ($request->has('type')) {
            $query->whereAccountType($request->input('type'));
        }
        
        if ($request->has('allow_manual')) {
            $query->where('allow_manual_entry', $request->boolean('allow_manual'));
        }
        
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('account_id', 'like', "%{$search}%")
                  ->orWhere('account_description', 'like', "%{$search}%");
            });
        }
        
        // Filter by segment values
        if ($request->has('segments')) {
            foreach ($request->input('segments', []) as $position => $valueId) {
                if ($valueId) {
                    $query->whereHas('segmentAssignments', function ($q) use ($valueId) {
                        $q->where('segment_value_id', $valueId);
                    });
                }
            }
        }
        
        $accounts = $query->paginate($request->input('per_page', 50));
        
        return $this->paginated($accounts);
    }
    
    /**
     * Get account details
     */
    public function show($accountId)
    {
        $account = GlAccount::where('account_id', $accountId)
            ->forTeam()
            ->with(['segmentAssignments.segmentValue.segmentDefinition'])
            ->firstOrFail();
        
        return $this->success([
            'account' => $account,
            'segments' => $account->segment_details,
            'balance' => GlAccountService::getAccountBalance($account),
        ]);
    }
    
    /**
     * Create account from segments
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'segment_value_ids' => 'required|array',
            'segment_value_ids.*' => 'required|integer|exists:fin_segment_values,id',
            'account_description' => 'required|string|max:255',
            'account_type' => 'required|in:asset,liability,equity,revenue,expense',
            'is_active' => 'boolean',
            'allow_manual_entry' => 'boolean',
        ]);
        
        try {
            DB::beginTransaction();
            
            $dto = new CreateAccountDto([
                'segment_value_ids' => $validated['segment_value_ids'],
                'account_description' => $validated['account_description'],
                'account_type' => $validated['account_type'],
                'is_active' => $validated['is_active'] ?? true,
                'allow_manual_entry' => $validated['allow_manual_entry'] ?? true,
                'team_id' => currentTeamId(),
            ]);
            
            $account = AccountSegmentService::createAccount($dto);
            
            DB::commit();
            
            return $this->success($account->load('segmentAssignments.segmentValue'), 'Account created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Update account
     */
    public function update(Request $request, $id)
    {
        $account = GlAccount::findOrFail($id);
        
        $validated = $request->validate([
            'account_description' => 'sometimes|nullable|string|max:255',
            'account_type' => 'sometimes|required|in:asset,liability,equity,revenue,expense',
            'is_active' => 'sometimes|boolean',
            'allow_manual_entry' => 'sometimes|boolean',
        ]);
        
        try {
            // Update account properties using property assignment
            if (isset($validated['account_description'])) {
                $account->account_description = $validated['account_description'];
            }
            if (isset($validated['account_type'])) {
                $account->account_type = $validated['account_type'];
            }
            if (isset($validated['is_active'])) {
                $account->is_active = $validated['is_active'];
            }
            if (isset($validated['allow_manual_entry'])) {
                $account->allow_manual_entry = $validated['allow_manual_entry'];
            }
            
            $account->save();
            
            return $this->success($account->load('segmentAssignments.segmentValue'), 'Account updated successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Get account balance
     */
    public function balance(Request $request, $accountId)
    {
        $account = GlAccount::where('account_id', $accountId)->forTeam()->firstOrFail();
        
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);
        
        $startDate = $validated['start_date'] ? \Carbon\Carbon::parse($validated['start_date']) : null;
        $endDate = $validated['end_date'] ? \Carbon\Carbon::parse($validated['end_date']) : null;
        
        $balance = GlAccountService::getAccountBalance($account, $startDate, $endDate);
        
        return $this->success([
            'account_id' => $account->account_id,
            'balance' => $balance->toFloat(),
            'formatted_balance' => $balance->formatted(),
            'period' => [
                'start_date' => $startDate?->format('Y-m-d'),
                'end_date' => $endDate?->format('Y-m-d'),
            ],
        ]);
    }
    
    /**
     * Get trial balance
     */
    public function trialBalance(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'account_types' => 'nullable|array',
            'account_types.*' => 'in:asset,liability,equity,revenue,expense',
            'active_only' => 'boolean',
        ]);
        
        $startDate = $validated['start_date'] ? \Carbon\Carbon::parse($validated['start_date']) : null;
        $endDate = $validated['end_date'] ? \Carbon\Carbon::parse($validated['end_date']) : null;
        
        $trialBalance = GlAccountService::getTrialBalance(
            $startDate,
            $endDate,
            currentTeamId(),
            $validated['account_types'] ?? null,
            $validated['active_only'] ?? true
        );
        
        return $this->success([
            'trial_balance' => $trialBalance,
            'summary' => [
                'total_debits' => $trialBalance->sum('debit_balance'),
                'total_credits' => $trialBalance->sum('credit_balance'),
                'is_balanced' => abs($trialBalance->sum('debit_balance') - $trialBalance->sum('credit_balance')) < 0.01,
            ],
            'period' => [
                'start_date' => $startDate?->format('Y-m-d'),
                'end_date' => $endDate?->format('Y-m-d'),
            ],
        ]);
    }
    
    /**
     * Bulk create accounts
     */
    public function bulkCreate(Request $request)
    {
        $validated = $request->validate([
            'accounts' => 'required|array',
            'accounts.*.segment_value_ids' => 'required|array',
            'accounts.*.segment_value_ids.*' => 'required|integer|exists:fin_segment_values,id',
            'accounts.*.account_description' => 'required|string|max:255',
            'accounts.*.account_type' => 'required|in:asset,liability,equity,revenue,expense',
            'accounts.*.is_active' => 'boolean',
            'accounts.*.allow_manual_entry' => 'boolean',
        ]);
        
        $created = [];
        $errors = [];
        
        DB::beginTransaction();
        
        try {
            foreach ($validated['accounts'] as $index => $accountData) {
                try {
                    // Check if account already exists
                    $existingAccount = AccountSegmentService::findAccountBySegmentValues(
                        $accountData['segment_value_ids'],
                        currentTeamId()
                    );
                    
                    if ($existingAccount) {
                        $errors[] = [
                            'index' => $index,
                            'error' => 'Account already exists with ID: ' . $existingAccount->account_id,
                        ];
                        continue;
                    }
                    
                    $dto = new CreateAccountDto([
                        'segment_value_ids' => $accountData['segment_value_ids'],
                        'account_description' => $accountData['account_description'],
                        'account_type' => $accountData['account_type'],
                        'is_active' => $accountData['is_active'] ?? true,
                        'allow_manual_entry' => $accountData['allow_manual_entry'] ?? true,
                        'team_id' => currentTeamId(),
                    ]);
                    
                    $account = AccountSegmentService::createAccount($dto);
                    $created[] = $account;
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'error' => $e->getMessage(),
                    ];
                }
            }
            
            if (empty($created) && !empty($errors)) {
                DB::rollBack();
                return $this->error('No accounts could be created', 400, $errors);
            }
            
            DB::commit();
            
            return $this->success([
                'created' => $created,
                'errors' => $errors,
                'summary' => [
                    'total' => count($validated['accounts']),
                    'created' => count($created),
                    'failed' => count($errors),
                ],
            ], 'Bulk creation completed');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Search accounts by segment pattern
     */
    public function searchByPattern(Request $request)
    {
        $validated = $request->validate([
            'pattern' => 'required|array',
            'pattern.*' => 'nullable|string',
        ]);
        
        $accounts = AccountSegmentService::searchAccountsByPattern(
            $validated['pattern'],
            currentTeamId()
        );
        
        return $this->success($accounts);
    }
}
