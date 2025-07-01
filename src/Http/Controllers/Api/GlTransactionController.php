<?php

namespace Condoedge\Finance\Http\Controllers\Api;

use Condoedge\Finance\Models\GlTransactionHeader;
use Condoedge\Finance\Models\GlTransactionLine;
use Condoedge\Finance\Models\Dto\CreateGlTransactionDto;
use Condoedge\Finance\Models\Dto\Gl\CreateGlTransactionDto as GlCreateGlTransactionDto;
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
     * List GL transactions with filters
     */
    public function index(Request $request)
    {
        $query = GlTransactionHeader::forTeam()
            ->with(['lines', 'customer', 'fiscalPeriod']);
        
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
        
        if ($request->has('fiscal_year')) {
            $query->where('fiscal_year', $request->input('fiscal_year'));
        }
        
        if ($request->has('fiscal_period')) {
            $query->where('fiscal_period', $request->input('fiscal_period'));
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
                  ->orWhere('transaction_description', 'like', "%{$search}%")
                  ->orWhere('gl_transaction_id', 'like', "%{$search}%");
            });
        }
        
        $transactions = $query->orderBy('fiscal_date', 'desc')
            ->orderBy('gl_transaction_number', 'desc')
            ->paginate($request->input('per_page', 50));
        
        return $this->paginated($transactions);
    }
    
    /**
     * Get transaction details
     */
    public function show($transactionId)
    {
        $transaction = GlTransactionHeader::where('gl_transaction_id', $transactionId)
            ->forTeam()
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
     * Create GL transaction
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'fiscal_date' => 'required|date',
            'transaction_description' => 'nullable|string|max:500',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|string|exists:fin_gl_accounts,account_id',
            'lines.*.line_description' => 'nullable|string|max:255',
            'lines.*.debit_amount' => 'required|numeric|min:0',
            'lines.*.credit_amount' => 'required|numeric|min:0',
        ]);
        
        // Validate that each line has either debit or credit, not both
        foreach ($validated['lines'] as $index => $line) {
            if ($line['debit_amount'] > 0 && $line['credit_amount'] > 0) {
                return $this->validationError([
                    "lines.{$index}" => ['A line cannot have both debit and credit amounts'],
                ]);
            }
            if ($line['debit_amount'] == 0 && $line['credit_amount'] == 0) {
                return $this->validationError([
                    "lines.{$index}" => ['A line must have either a debit or credit amount'],
                ]);
            }
        }
        
        // Validate balance
        $totalDebits = collect($validated['lines'])->sum('debit_amount');
        $totalCredits = collect($validated['lines'])->sum('credit_amount');
        
        if (abs($totalDebits - $totalCredits) > 0.01) {
            return $this->validationError([
                'lines' => ['Transaction must balance. Debits: ' . $totalDebits . ', Credits: ' . $totalCredits],
            ]);
        }
        
        try {
            DB::beginTransaction();
            
            $dto = new GlCreateGlTransactionDto($validated);
            $transaction = $this->transactionService->createTransaction($dto);
            
            DB::commit();
            
            return $this->success(
                $transaction->load(['lines.account', 'fiscalPeriod']),
                'GL transaction created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Update GL transaction
     */
    public function update(Request $request, $transactionId)
    {
        $transaction = GlTransactionHeader::where('gl_transaction_id', $transactionId)
            ->forTeam()
            ->firstOrFail();
        
        if (!$transaction->canBeModified()) {
            return $this->error('Transaction cannot be modified', 403);
        }
        
        $validated = $request->validate([
            'fiscal_date' => 'sometimes|required|date',
            'transaction_description' => 'nullable|string|max:500',
            'lines' => 'sometimes|required|array|min:2',
            'lines.*.id' => 'nullable|exists:fin_gl_transaction_lines,id',
            'lines.*.account_id' => 'required|string|exists:fin_gl_accounts,account_id',
            'lines.*.line_description' => 'nullable|string|max:255',
            'lines.*.debit_amount' => 'required|numeric|min:0',
            'lines.*.credit_amount' => 'required|numeric|min:0',
        ]);
        
        try {
            DB::beginTransaction();
            
            // Update header
            if (isset($validated['fiscal_date']) || isset($validated['transaction_description'])) {
                $headerData = array_intersect_key($validated, array_flip(['fiscal_date', 'transaction_description']));
                
                // Re-determine fiscal year and period if date changed
                if (isset($validated['fiscal_date'])) {
                    $fiscalData = GlTransactionHeader::determineFiscalData($validated['fiscal_date']);
                    $headerData['fiscal_year'] = $fiscalData['fiscal_year'];
                    $headerData['fiscal_period'] = $fiscalData['fiscal_period'];
                }
                
                $transaction->update($headerData);
            }
            
            // Update lines if provided
            if (isset($validated['lines'])) {
                // Validate balance
                $totalDebits = collect($validated['lines'])->sum('debit_amount');
                $totalCredits = collect($validated['lines'])->sum('credit_amount');
                
                if (abs($totalDebits - $totalCredits) > 0.01) {
                    throw new \Exception('Transaction must balance');
                }
                
                // Delete existing lines
                $transaction->lines()->delete();
                
                // Create new lines
                foreach ($validated['lines'] as $lineData) {
                    $transaction->lines()->create([
                        'account_id' => $lineData['account_id'],
                        'line_description' => $lineData['line_description'] ?? null,
                        'debit_amount' => $lineData['debit_amount'],
                        'credit_amount' => $lineData['credit_amount'],
                    ]);
                }
            }
            
            DB::commit();
            
            return $this->success(
                $transaction->fresh(['lines.account', 'fiscalPeriod']),
                'GL transaction updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Post GL transaction
     */
    public function post($transactionId)
    {
        $transaction = GlTransactionHeader::where('gl_transaction_id', $transactionId)
            ->forTeam()
            ->firstOrFail();
        
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
     * Get transaction by account
     */
    public function byAccount(Request $request, $accountId)
    {
        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'posted_only' => 'boolean',
        ]);
        
        $query = GlTransactionLine::where('account_id', $accountId)
            ->whereHas('header', function ($q) {
                $q->forTeam();
            })
            ->with(['header.fiscalPeriod']);
        
        if ($validated['date_from'] ?? false) {
            $query->whereHas('header', function ($q) use ($validated) {
                $q->where('fiscal_date', '>=', $validated['date_from']);
            });
        }
        
        if ($validated['date_to'] ?? false) {
            $query->whereHas('header', function ($q) use ($validated) {
                $q->where('fiscal_date', '<=', $validated['date_to']);
            });
        }
        
        if ($validated['posted_only'] ?? false) {
            $query->whereHas('header', function ($q) {
                $q->where('is_posted', true);
            });
        }
        
        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 50));
        
        // Calculate running balance
        $runningBalance = 0;
        $transactions->getCollection()->transform(function ($line) use (&$runningBalance) {
            $runningBalance += $line->debit_amount - $line->credit_amount;
            $line->running_balance = $runningBalance;
            return $line;
        });
        
        return $this->paginated($transactions);
    }
    
    /**
     * Get unbalanced transactions
     */
    public function unbalanced(Request $request)
    {
        $transactions = GlTransactionHeader::forTeam()
            ->unbalanced()
            ->with(['lines', 'fiscalPeriod'])
            ->orderBy('fiscal_date', 'desc')
            ->paginate($request->input('per_page', 50));
        
        return $this->paginated($transactions);
    }
    
    /**
     * Validate a transaction before saving
     */
    public function validate(Request $request)
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
