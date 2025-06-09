<?php

namespace Condoedge\Finance\Http\Controllers;

use Condoedge\Finance\Services\GlTransactionService;
use Condoedge\Finance\Models\GlTransactionHeader;
use Condoedge\Finance\Models\Account;
use Condoedge\Finance\Models\FiscalPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class GlTransactionController extends Controller
{
    protected $glService;
    
    public function __construct(GlTransactionService $glService)
    {
        $this->glService = $glService;
    }
    
    /**
     * Display listing of GL transactions
     */
    public function index(Request $request): JsonResponse
    {
        $query = GlTransactionHeader::with(['lines.account', 'fiscalPeriod']);
        
        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('fiscal_date', '>=', $request->start_date);
        }
        
        if ($request->has('end_date')) {
            $query->where('fiscal_date', '<=', $request->end_date);
        }
        
        // Filter by transaction type
        if ($request->has('transaction_type')) {
            $query->where('gl_transaction_type', $request->transaction_type);
        }
        
        // Filter by posting status
        if ($request->has('posted_only') && $request->posted_only) {
            $query->posted();
        }
        
        // Filter by balance status
        if ($request->has('unbalanced_only') && $request->unbalanced_only) {
            $query->unbalanced();
        }
        
        $transactions = $query->paginate($request->per_page ?? 50);
        
        return response()->json($transactions);
    }
    
    /**
     * Show specific GL transaction
     */
    public function show(string $transactionId): JsonResponse
    {
        $transaction = GlTransactionHeader::with(['lines.account', 'fiscalPeriod', 'customer'])
            ->findOrFail($transactionId);
            
        return response()->json($transaction);
    }
    
    /**
     * Create new GL transaction
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fiscal_date' => 'required|date',
            'gl_transaction_type' => 'required|integer|between:1,4',
            'transaction_description' => 'required|string|max:500',
            'customer_id' => 'nullable|exists:fin_customers,id',
            'vendor_id' => 'nullable|integer',
            'team_id' => 'nullable|integer',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:fin_gl_accounts,account_id',
            'lines.*.line_description' => 'nullable|string|max:500',
            'lines.*.debit_amount' => 'required|numeric|min:0',
            'lines.*.credit_amount' => 'required|numeric|min:0',
        ]);
        
        try {
            $transaction = $this->glService->createTransaction(
                $validated,
                $validated['lines']
            );
            
            return response()->json([
                'message' => 'Transaction created successfully',
                'transaction' => $transaction->load(['lines.account'])
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create transaction',
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Update GL transaction (only if unposted)
     */
    public function update(Request $request, string $transactionId): JsonResponse
    {
        $transaction = GlTransactionHeader::findOrFail($transactionId);
        
        if (!$transaction->canBeModified()) {
            return response()->json([
                'error' => 'Transaction cannot be modified'
            ], 403);
        }
        
        $validated = $request->validate([
            'transaction_description' => 'sometimes|string|max:500',
            'lines' => 'sometimes|array|min:2',
            'lines.*.account_id' => 'required_with:lines|exists:fin_gl_accounts,account_id',
            'lines.*.line_description' => 'nullable|string|max:500',
            'lines.*.debit_amount' => 'required_with:lines|numeric|min:0',
            'lines.*.credit_amount' => 'required_with:lines|numeric|min:0',
        ]);
        
        try {
            // Update header if provided
            if (isset($validated['transaction_description'])) {
                $transaction->update(['transaction_description' => $validated['transaction_description']]);
            }
            
            // Update lines if provided
            if (isset($validated['lines'])) {
                // Delete existing lines
                $transaction->lines()->delete();
                
                // Create new lines
                foreach ($validated['lines'] as $lineData) {
                    $this->glService->createTransactionLine(
                        array_merge($lineData, ['gl_transaction_id' => $transaction->gl_transaction_id])
                    );
                }
            }
            
            return response()->json([
                'message' => 'Transaction updated successfully',
                'transaction' => $transaction->fresh()->load(['lines.account'])
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update transaction',
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Post a transaction
     */
    public function post(string $transactionId): JsonResponse
    {
        try {
            $transaction = $this->glService->postTransaction($transactionId);
            
            return response()->json([
                'message' => 'Transaction posted successfully',
                'transaction' => $transaction
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to post transaction',
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Reverse a posted transaction
     */
    public function reverse(Request $request, string $transactionId): JsonResponse
    {
        $validated = $request->validate([
            'reversal_description' => 'nullable|string|max:500'
        ]);
        
        try {
            $reversalTransaction = $this->glService->reverseTransaction(
                $transactionId,
                $validated['reversal_description'] ?? null
            );
            
            return response()->json([
                'message' => 'Transaction reversed successfully',
                'reversal_transaction' => $reversalTransaction->load(['lines.account'])
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to reverse transaction',
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Delete unposted transaction
     */
    public function destroy(string $transactionId): JsonResponse
    {
        $transaction = GlTransactionHeader::findOrFail($transactionId);
        
        if (!$transaction->canBeModified()) {
            return response()->json([
                'error' => 'Transaction cannot be deleted'
            ], 403);
        }
        
        try {
            $transaction->delete();
            
            return response()->json([
                'message' => 'Transaction deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete transaction',
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Get trial balance
     */
    public function trialBalance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'posted_only' => 'sometimes|boolean'
        ]);
        
        try {
            $trialBalance = $this->glService->getTrialBalance(
                \Carbon\Carbon::parse($validated['start_date']),
                \Carbon\Carbon::parse($validated['end_date']),
                $validated['posted_only'] ?? true
            );
            
            return response()->json([
                'trial_balance' => $trialBalance,
                'period' => [
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date']
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate trial balance',
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Get account balance
     */
    public function accountBalance(Request $request, string $accountId): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'posted_only' => 'sometimes|boolean'
        ]);
        
        try {
            $startDate = $validated['start_date'] ? \Carbon\Carbon::parse($validated['start_date']) : null;
            $endDate = $validated['end_date'] ? \Carbon\Carbon::parse($validated['end_date']) : null;
            
            $balance = $this->glService->getAccountBalance(
                $accountId,
                $startDate,
                $endDate,
                $validated['posted_only'] ?? true
            );
            
            $account = Account::findOrFail($accountId);
            
            return response()->json([
                'account' => $account,
                'balance' => $balance,
                'period' => [
                    'start_date' => $validated['start_date'] ?? null,
                    'end_date' => $validated['end_date'] ?? null
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get account balance',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
