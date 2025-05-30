<?php

namespace Condoedge\Finance\Http\Controllers\GL;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Condoedge\Finance\Services\GL\GlTransactionService;
use Condoedge\Finance\Models\GL\GlTransaction;
use Carbon\Carbon;

class GlTransactionController extends Controller
{
    protected GlTransactionService $glTransactionService;

    public function __construct(GlTransactionService $glTransactionService)
    {
        $this->glTransactionService = $glTransactionService;
    }

    /**
     * Get GL transactions
     */
    public function index(Request $request)
    {
        $filters = $request->only([
            'fiscal_date_from',
            'fiscal_date_to',
            'fiscal_year',
            'fiscal_period',
            'transaction_type',
            'account_id',
            'customer_id',
            'vendor_id'
        ]);

        try {
            $transactions = $this->glTransactionService->getGlTransactions($filters);

            return response()->json([
                'data' => $transactions,
                'message' => 'GL transactions retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve GL transactions',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get specific GL transaction
     */
    public function show($transactionId)
    {
        try {
            $transaction = $this->glTransactionService->getGlTransaction($transactionId);

            return response()->json([
                'data' => $transaction,
                'message' => 'GL transaction retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve GL transaction',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Create manual GL transaction
     */
    public function createManualTransaction(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
            'fiscal_date' => 'required|date',
            'entries' => 'required|array|min:2',
            'entries.*.account_id' => 'required|string|exists:fin_accounts,account_id',
            'entries.*.debit_amount' => 'nullable|numeric|min:0',
            'entries.*.credit_amount' => 'nullable|numeric|min:0',
            'entries.*.line_description' => 'nullable|string|max:255',
            'customer_id' => 'nullable|exists:fin_customers,id',
            'vendor_id' => 'nullable|exists:fin_vendors,id',
            'team_id' => 'nullable|integer'
        ]);

        // Validate that each entry has either debit or credit
        foreach ($request->entries as $index => $entry) {
            $debit = $entry['debit_amount'] ?? 0;
            $credit = $entry['credit_amount'] ?? 0;
            
            if ($debit == 0 && $credit == 0) {
                return response()->json([
                    'error' => 'Validation failed',
                    'message' => "Entry at index {$index} must have either debit or credit amount"
                ], 422);
            }
            
            if ($debit > 0 && $credit > 0) {
                return response()->json([
                    'error' => 'Validation failed',
                    'message' => "Entry at index {$index} cannot have both debit and credit amounts"
                ], 422);
            }
        }

        try {
            $additionalData = $request->only(['customer_id', 'vendor_id', 'team_id']);
            
            $transaction = $this->glTransactionService->createManualGlTransaction(
                $request->description,
                Carbon::parse($request->fiscal_date),
                $request->entries,
                $additionalData
            );

            return response()->json([
                'data' => $this->glTransactionService->getGlTransaction($transaction->id),
                'message' => 'Manual GL transaction created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create GL transaction',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update GL transaction
     */
    public function update(Request $request, $transactionId)
    {
        $request->validate([
            'transaction_description' => 'string|max:255',
            'fiscal_date' => 'date',
            'entries' => 'array|min:2',
            'entries.*.account_id' => 'required_with:entries|string|exists:fin_accounts,account_id',
            'entries.*.debit_amount' => 'nullable|numeric|min:0',
            'entries.*.credit_amount' => 'nullable|numeric|min:0',
            'entries.*.line_description' => 'nullable|string|max:255'
        ]);

        // Validate entries if provided
        if ($request->has('entries')) {
            foreach ($request->entries as $index => $entry) {
                $debit = $entry['debit_amount'] ?? 0;
                $credit = $entry['credit_amount'] ?? 0;
                
                if ($debit == 0 && $credit == 0) {
                    return response()->json([
                        'error' => 'Validation failed',
                        'message' => "Entry at index {$index} must have either debit or credit amount"
                    ], 422);
                }
                
                if ($debit > 0 && $credit > 0) {
                    return response()->json([
                        'error' => 'Validation failed',
                        'message' => "Entry at index {$index} cannot have both debit and credit amounts"
                    ], 422);
                }
            }
        }

        try {
            $data = $request->only(['transaction_description', 'fiscal_date', 'entries']);
            
            $transaction = $this->glTransactionService->updateGlTransaction($transactionId, $data);

            return response()->json([
                'data' => $this->glTransactionService->getGlTransaction($transaction->id),
                'message' => 'GL transaction updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update GL transaction',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Delete GL transaction
     */
    public function destroy($transactionId)
    {
        try {
            $result = $this->glTransactionService->deleteGlTransaction($transactionId);

            return response()->json([
                'data' => $result,
                'message' => 'GL transaction deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete GL transaction',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Reverse GL transaction
     */
    public function reverse(Request $request, $transactionId)
    {
        $request->validate([
            'reversal_reason' => 'required|string|max:255'
        ]);

        try {
            $reversalTransaction = $this->glTransactionService->reverseGlTransaction(
                $transactionId,
                $request->reversal_reason
            );

            return response()->json([
                'data' => $this->glTransactionService->getGlTransaction($reversalTransaction->id),
                'message' => 'GL transaction reversed successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to reverse GL transaction',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Validate GL transaction balance
     */
    public function validateBalance($transactionId)
    {
        try {
            $transaction = GlTransaction::findOrFail($transactionId);
            $isBalanced = $transaction->validateBalance();

            return response()->json([
                'data' => [
                    'transaction_id' => $transaction->id,
                    'gl_transaction_number' => $transaction->gl_transaction_number,
                    'is_balanced' => $isBalanced,
                    'total_debits' => $transaction->glEntries()->sum('debit_amount'),
                    'total_credits' => $transaction->glEntries()->sum('credit_amount')
                ],
                'message' => $isBalanced ? 'Transaction is balanced' : 'Transaction is not balanced'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to validate transaction balance',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get next GL transaction number
     */
    public function getNextTransactionNumber()
    {
        try {
            $nextNumber = GlTransaction::getNextTransactionNumber();

            return response()->json([
                'data' => [
                    'next_transaction_number' => $nextNumber
                ],
                'message' => 'Next transaction number retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get next transaction number',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Create system GL transaction (for other modules)
     */
    public function createSystemTransaction(Request $request)
    {
        $request->validate([
            'transaction_type' => 'required|integer|in:2,3,4', // Bank, Receivable, Payable
            'description' => 'required|string|max:255',
            'fiscal_date' => 'required|date',
            'entries' => 'required|array|min:2',
            'entries.*.account_id' => 'required|string|exists:fin_accounts,account_id',
            'entries.*.debit_amount' => 'nullable|numeric|min:0',
            'entries.*.credit_amount' => 'nullable|numeric|min:0',
            'entries.*.line_description' => 'nullable|string|max:255',
            'originating_module_transaction_id' => 'nullable|string',
            'customer_id' => 'nullable|exists:fin_customers,id',
            'vendor_id' => 'nullable|exists:fin_vendors,id',
            'team_id' => 'nullable|integer'
        ]);

        // Validate that each entry has either debit or credit
        foreach ($request->entries as $index => $entry) {
            $debit = $entry['debit_amount'] ?? 0;
            $credit = $entry['credit_amount'] ?? 0;
            
            if ($debit == 0 && $credit == 0) {
                return response()->json([
                    'error' => 'Validation failed',
                    'message' => "Entry at index {$index} must have either debit or credit amount"
                ], 422);
            }
            
            if ($debit > 0 && $credit > 0) {
                return response()->json([
                    'error' => 'Validation failed',
                    'message' => "Entry at index {$index} cannot have both debit and credit amounts"
                ], 422);
            }
        }

        try {
            $additionalData = $request->only([
                'originating_module_transaction_id',
                'customer_id',
                'vendor_id',
                'team_id'
            ]);
            
            $transaction = $this->glTransactionService->createSystemGlTransaction(
                $request->transaction_type,
                $request->description,
                Carbon::parse($request->fiscal_date),
                $request->entries,
                $additionalData
            );

            return response()->json([
                'data' => $this->glTransactionService->getGlTransaction($transaction->id),
                'message' => 'System GL transaction created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create system GL transaction',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
