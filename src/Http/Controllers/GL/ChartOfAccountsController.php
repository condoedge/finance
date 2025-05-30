<?php

namespace Condoedge\Finance\Http\Controllers\GL;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Condoedge\Finance\Services\GL\ChartOfAccountsService;
use Condoedge\Finance\Models\GL\GlAccount;
use Condoedge\Finance\Models\GL\AccountSegmentDefinition;
use Condoedge\Finance\Models\GL\GlSegmentValue;
use Condoedge\Finance\Models\GL\CompanyDefaultAccount;

class ChartOfAccountsController extends Controller
{
    protected ChartOfAccountsService $chartOfAccountsService;

    public function __construct(ChartOfAccountsService $chartOfAccountsService)
    {
        $this->chartOfAccountsService = $chartOfAccountsService;
    }

    /**
     * Setup account structure
     */
    public function setupAccountStructure(Request $request)
    {
        $request->validate([
            'segments' => 'required|array|min:1|max:5',
            'segments.*.name' => 'required|string|max:50',
            'segments.*.length' => 'required|integer|min:1|max:10',
            'segments.*.description' => 'nullable|string|max:255'
        ]);

        try {
            $result = $this->chartOfAccountsService->setupAccountStructure($request->segments);

            return response()->json([
                'data' => $result,
                'message' => 'Account structure setup successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to setup account structure',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get account structure
     */
    public function getAccountStructure()
    {
        $definitions = AccountSegmentDefinition::getActiveDefinitions();
        
        return response()->json([
            'data' => $definitions,
            'message' => 'Account structure retrieved successfully'
        ]);
    }

    /**
     * Create segment value
     */
    public function createSegmentValue(Request $request)
    {
        $request->validate([
            'segment_number' => 'required|integer|min:1|max:5',
            'segment_value' => 'required|string',
            'segment_description' => 'required|string|max:255'
        ]);

        try {
            $segmentValue = $this->chartOfAccountsService->createSegmentValue(
                $request->segment_number,
                $request->segment_value,
                $request->segment_description
            );

            return response()->json([
                'data' => $segmentValue,
                'message' => 'Segment value created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create segment value',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get segment values
     */
    public function getSegmentValues($segmentNumber)
    {
        $segmentValues = GlSegmentValue::getSegmentValues($segmentNumber);
        
        return response()->json([
            'data' => $segmentValues,
            'message' => 'Segment values retrieved successfully'
        ]);
    }

    /**
     * Create GL account
     */
    public function createGlAccount(Request $request)
    {
        $request->validate([
            'segments' => 'required|array',
            'description' => 'required|string|max:255',
            'account_type' => 'nullable|in:Asset,Liability,Equity,Revenue,Expense',
            'account_category' => 'nullable|string|max:100',
            'allow_manual_entry' => 'boolean'
        ]);

        try {
            $account = $this->chartOfAccountsService->createGlAccount(
                $request->segments,
                $request->description,
                $request->account_type,
                $request->account_category,
                $request->allow_manual_entry ?? true
            );

            return response()->json([
                'data' => $account,
                'message' => 'GL account created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create GL account',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get chart of accounts
     */
    public function getChartOfAccounts(Request $request)
    {
        $filters = $request->only(['account_type', 'segment1', 'segment2', 'segment3']);
        
        try {
            $chartOfAccounts = $this->chartOfAccountsService->getChartOfAccounts($filters);

            return response()->json([
                'data' => $chartOfAccounts,
                'message' => 'Chart of accounts retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve chart of accounts',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get accounts for selection dropdown
     */
    public function getAccountsForSelection(Request $request)
    {
        $manualEntryOnly = $request->boolean('manual_entry_only', false);
        
        try {
            $accounts = $this->chartOfAccountsService->getAccountsForSelection($manualEntryOnly);

            return response()->json([
                'data' => $accounts,
                'message' => 'Accounts for selection retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve accounts',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update GL account
     */
    public function updateGlAccount(Request $request, $accountId)
    {
        $request->validate([
            'description' => 'string|max:255',
            'account_type' => 'nullable|in:Asset,Liability,Equity,Revenue,Expense',
            'account_category' => 'nullable|string|max:100',
            'allow_manual_entry' => 'boolean'
        ]);

        try {
            $account = GlAccount::where('account_id', $accountId)->firstOrFail();
            
            if ($request->has('description')) {
                $account->account_description = $request->description;
            }
            
            if ($request->has('account_type')) {
                $account->account_type = $request->account_type;
            }
            
            if ($request->has('account_category')) {
                $account->account_category = $request->account_category;
            }
            
            if ($request->has('allow_manual_entry')) {
                $account->allow_manual_entry = $request->allow_manual_entry;
            }
            
            $account->save();

            return response()->json([
                'data' => $account,
                'message' => 'GL account updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update GL account',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Disable GL account
     */
    public function disableAccount($accountId)
    {
        try {
            $result = $this->chartOfAccountsService->disableAccount($accountId);

            return response()->json([
                'data' => $result,
                'message' => 'Account disabled successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to disable account',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Enable GL account
     */
    public function enableAccount(Request $request, $accountId)
    {
        $request->validate([
            'allow_manual_entry' => 'boolean'
        ]);

        try {
            $result = $this->chartOfAccountsService->enableAccount(
                $accountId,
                $request->allow_manual_entry ?? true
            );

            return response()->json([
                'data' => $result,
                'message' => 'Account enabled successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to enable account',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get trial balance
     */
    public function getTrialBalance(Request $request)
    {
        $request->validate([
            'as_of_date' => 'nullable|date'
        ]);

        try {
            $asOfDate = $request->as_of_date ? \Carbon\Carbon::parse($request->as_of_date) : null;
            $trialBalance = $this->chartOfAccountsService->getTrialBalance($asOfDate);

            return response()->json([
                'data' => $trialBalance,
                'message' => 'Trial balance retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve trial balance',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Import chart of accounts
     */
    public function importChartOfAccounts(Request $request)
    {
        $request->validate([
            'accounts' => 'required|array',
            'accounts.*.segments' => 'required|array',
            'accounts.*.description' => 'required|string',
            'accounts.*.account_type' => 'nullable|in:Asset,Liability,Equity,Revenue,Expense',
            'accounts.*.account_category' => 'nullable|string',
            'accounts.*.allow_manual_entry' => 'boolean'
        ]);

        try {
            $results = $this->chartOfAccountsService->importChartOfAccounts($request->accounts);

            return response()->json([
                'data' => $results,
                'message' => "Import completed. {$results['success']} accounts created successfully."
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to import chart of accounts',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get company default accounts
     */
    public function getDefaultAccounts()
    {
        $defaults = CompanyDefaultAccount::getActiveDefaults();
        
        return response()->json([
            'data' => $defaults,
            'message' => 'Default accounts retrieved successfully'
        ]);
    }

    /**
     * Set company default account
     */
    public function setDefaultAccount(Request $request)
    {
        $request->validate([
            'setting_name' => 'required|string',
            'account_id' => 'required|string|exists:fin_accounts,account_id',
            'description' => 'nullable|string'
        ]);

        try {
            $result = CompanyDefaultAccount::setDefaultAccount(
                $request->setting_name,
                $request->account_id,
                $request->description
            );

            return response()->json([
                'data' => $result,
                'message' => 'Default account set successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to set default account',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get account balance
     */
    public function getAccountBalance(Request $request, $accountId)
    {
        $request->validate([
            'as_of_date' => 'nullable|date'
        ]);

        try {
            $account = GlAccount::where('account_id', $accountId)->firstOrFail();
            $asOfDate = $request->as_of_date ? \Carbon\Carbon::parse($request->as_of_date) : null;
            $balance = $account->getBalance($asOfDate);

            return response()->json([
                'data' => [
                    'account_id' => $account->account_id,
                    'account_description' => $account->account_description,
                    'account_type' => $account->account_type,
                    'balance' => $balance,
                    'as_of_date' => $asOfDate?->format('Y-m-d') ?? 'Current'
                ],
                'message' => 'Account balance retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve account balance',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
