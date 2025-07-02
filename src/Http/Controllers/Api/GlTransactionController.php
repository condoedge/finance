<?php

namespace Condoedge\Finance\Http\Controllers\Api;

use Condoedge\Finance\Models\Dto\Gl\CreateGlTransactionDto;
use Condoedge\Finance\Models\GlTransactionHeader;

use Condoedge\Finance\Services\GlTransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GlTransactionController extends ApiController
{
    protected GlTransactionService $transactionService;
    
    public function __construct(GlTransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }
    
    /**
     * @operationId List GL transactions with filters
     */
    public function index(Request $request)
    {
        $query = GlTransactionHeader::with(['lines', 'customer', 'fiscalPeriod']);
        
        // Apply filters
        if ($request->has('type')) {
            $query->where('gl_transaction_type', $request->input('type'));
        }
        
        if ($request->has('date_from')) {
            $query->where('fiscal_date', '>=', $request->input('date_from'));
        }
        
        if ($request->has('date_to')) {
            $query->where('fiscal_date', '<=', $request->input('date_to'));
        }
        
        if ($request->has('fiscal_period_id')) {
            $query->where('fiscal_period_id', $request->input('fiscal_period_id'));
        }
        
        if ($request->has('balanced')) {
            $query->where('is_balanced', $request->boolean('balanced'));
        }
        
        if ($request->has('posted')) {
            $query->where('is_posted', $request->boolean('posted'));
        }
        
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('gl_transaction_number', 'like', "%{$search}%")
                  ->orWhere('transaction_description', 'like', "%{$search}%");
            });
        }
        
        $transactions = $query->orderBy('fiscal_date', 'desc')
            ->orderBy('gl_transaction_number', 'desc')
            ->paginate($request->input('per_page', 50));
        
        return $this->paginated($transactions);
    }
    
    /**
     * @operationId Get transaction details
     */
    public function show($transactionId)
    {
        $transaction = GlTransactionHeader::where('id', $transactionId)
            ->with(['lines.account', 'customer', 'fiscalPeriod'])
            ->firstOrFail();
        
        return $this->success([
            'transaction' => $transaction,
            'totals' => [
                'debits' => $transaction->total_debits,
                'credits' => $transaction->total_credits,
                'is_balanced' => $transaction->is_balanced,
            ],
            'can_modify' => $transaction->canBeModified(),
        ]);
    }
    
    /**
     * @operationId Create GL transaction
     */
    public function store(CreateGlTransactionDto $data)
    {
        try {
            $this->transactionService->createTransaction($data);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * @operationId Post GL transaction
     */
    public function post($transactionId)
    {
        $transaction = GlTransactionHeader::findOrFail('id', $transactionId);
        
        try {
            $this->transactionService->postTransaction($transaction);
            
            return $this->success(
                $transaction->fresh(['lines.account', 'fiscalPeriod']),
                'Transaction posted successfully'
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * @operationId Get transactions by a natural account
     */
    public function byAccount(Request $request)
    {
        $transactions = GlTransactionHeader::byNaturalAccount($request->input('account_id'))
            ->with(['lines', 'fiscalPeriod'])
            ->orderBy('fiscal_date', 'desc')
            ->paginate($request->input('per_page', 50));
        
        return $this->paginated($transactions);
    }
    
    /**
     * @operationId Get unbalanced transactions
     */
    public function unbalanced(Request $request)
    {
        $transactions = GlTransactionHeader::unbalanced()
            ->with(['lines', 'fiscalPeriod'])
            ->orderBy('fiscal_date', 'desc')
            ->paginate($request->input('per_page', 50));
        
        return $this->paginated($transactions);
    }
    
    /**
     * @operationId Validate a transaction before saving
     */
    public function validateStatus(Request $request)
    {
        $validated = $request->validate([
            'fiscal_date' => 'required|date',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|string',
            'lines.*.debit_amount' => 'required|numeric|min:0',
            'lines.*.credit_amount' => 'required|numeric|min:0',
        ]);
        
        $errors = [];
        
        // Check balance
        $totalDebits = collect($validated['lines'])->sum('debit_amount');
        $totalCredits = collect($validated['lines'])->sum('credit_amount');
        $isBalanced = abs($totalDebits - $totalCredits) < 0.01;
        
        if (!$isBalanced) {
            $errors[] = 'Transaction does not balance';
        }
        
        // Check fiscal period
        try {
            $fiscalData = GlTransactionHeader::determineFiscalData($validated['fiscal_date']);
            $period = \Condoedge\Finance\Models\FiscalPeriod::find($fiscalData['fiscal_period']);
            
            if (!$period || !$period->is_open_gl) {
                $errors[] = 'Fiscal period is closed for GL transactions';
            }
        } catch (\Exception $e) {
            $errors[] = 'Invalid fiscal date: ' . $e->getMessage();
        }
        
        // Check accounts exist and allow manual entry
        foreach ($validated['lines'] as $index => $line) {
            $account = \Condoedge\Finance\Models\GlAccount::where('account_id', $line['account_id'])
                ->forTeam()
                ->first();
                
            if (!$account) {
                $errors[] = "Line {$index}: Account {$line['account_id']} not found";
            } elseif (!$account->is_active) {
                $errors[] = "Line {$index}: Account {$line['account_id']} is inactive";
            } elseif (!$account->allow_manual_entry) {
                $errors[] = "Line {$index}: Account {$line['account_id']} does not allow manual entry";
            }
        }
        
        if (empty($errors)) {
            return $this->success([
                'valid' => true,
                'fiscal_year' => $fiscalData['fiscal_year'] ?? null,
                'fiscal_period' => $fiscalData['fiscal_period'] ?? null,
                'totals' => [
                    'debits' => $totalDebits,
                    'credits' => $totalCredits,
                ],
            ], 'Transaction is valid');
        }
        
        return $this->success([
            'valid' => false,
            'errors' => $errors,
            'totals' => [
                'debits' => $totalDebits,
                'credits' => $totalCredits,
            ],
        ], 'Transaction validation failed');
    }
}
