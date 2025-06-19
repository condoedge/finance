<?php

namespace Condoedge\Finance\Http\Controllers\Api;

use Condoedge\Finance\Models\CompanyDefaultAccount;
use Condoedge\Finance\Models\GlAccount;
use Illuminate\Http\Request;

class CompanyDefaultAccountController extends ApiController
{
    /**
     * Get all default accounts
     */
    public function index()
    {
        $defaults = CompanyDefaultAccount::forTeam()
            ->with('account')
            ->get()
            ->mapWithKeys(function ($default) {
                return [$default->setting_name => [
                    'account_id' => $default->account_id,
                    'account' => $default->account,
                ]];
            });
        
        // Define available default account types
        $availableTypes = [
            'default_revenue_account' => 'Default Revenue Account',
            'default_expense_account' => 'Default Expense Account',
            'default_bank_account' => 'Default Bank Account',
            'default_accounts_receivable' => 'Default Accounts Receivable',
            'default_accounts_payable' => 'Default Accounts Payable',
            'default_cogs_account' => 'Default Cost of Goods Sold',
            'default_tax_payable_account' => 'Default Tax Payable',
            'default_retained_earnings_account' => 'Default Retained Earnings',
        ];
        
        // Merge with defaults to show all available types
        $allDefaults = collect($availableTypes)->map(function ($description, $key) use ($defaults) {
            return [
                'setting_name' => $key,
                'description' => $description,
                'account_id' => $defaults[$key]['account_id'] ?? null,
                'account' => $defaults[$key]['account'] ?? null,
            ];
        });
        
        return $this->success($allDefaults);
    }
    
    /**
     * Get specific default account
     */
    public function show($settingName)
    {
        $default = CompanyDefaultAccount::where('setting_name', $settingName)
            ->forTeam()
            ->with('account')
            ->first();
        
        if (!$default) {
            return $this->success([
                'setting_name' => $settingName,
                'account_id' => null,
                'account' => null,
            ]);
        }
        
        return $this->success($default);
    }
    
    /**
     * Set default account
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'setting_name' => 'required|string|max:50',
            'account_id' => 'required|exists:fin_gl_accounts,account_id',
        ]);
        
        // Verify account belongs to team and is active
        $account = GlAccount::where('account_id', $validated['account_id'])
            ->forTeam()
            ->active()
            ->first();
            
        if (!$account) {
            return $this->error('Account not found or inactive', 404);
        }
        
        $default = CompanyDefaultAccount::updateOrCreate(
            [
                'setting_name' => $validated['setting_name'],
                'team_id' => currentTeamId(),
            ],
            [
                'account_id' => $validated['account_id'],
            ]
        );
        
        return $this->success(
            $default->load('account'),
            'Default account set successfully'
        );
    }
    
    /**
     * Update default account
     */
    public function update(Request $request, $settingName)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:fin_gl_accounts,account_id',
        ]);
        
        // Verify account belongs to team and is active
        $account = GlAccount::where('account_id', $validated['account_id'])
            ->forTeam()
            ->active()
            ->first();
            
        if (!$account) {
            return $this->error('Account not found or inactive', 404);
        }
        
        $default = CompanyDefaultAccount::updateOrCreate(
            [
                'setting_name' => $settingName,
                'team_id' => currentTeamId(),
            ],
            [
                'account_id' => $validated['account_id'],
            ]
        );
        
        return $this->success(
            $default->load('account'),
            'Default account updated successfully'
        );
    }
    
    /**
     * Delete default account
     */
    public function destroy($settingName)
    {
        $deleted = CompanyDefaultAccount::where('setting_name', $settingName)
            ->forTeam()
            ->delete();
        
        if (!$deleted) {
            return $this->error('Default account not found', 404);
        }
        
        return $this->success(null, 'Default account removed successfully');
    }
    
    /**
     * Bulk set default accounts
     */
    public function bulkSet(Request $request)
    {
        $validated = $request->validate([
            'defaults' => 'required|array',
            'defaults.*.setting_name' => 'required|string|max:50',
            'defaults.*.account_id' => 'required|exists:fin_gl_accounts,account_id',
        ]);
        
        $results = [];
        $errors = [];
        
        foreach ($validated['defaults'] as $index => $defaultData) {
            try {
                // Verify account
                $account = GlAccount::where('account_id', $defaultData['account_id'])
                    ->forTeam()
                    ->active()
                    ->first();
                    
                if (!$account) {
                    throw new \Exception('Account not found or inactive');
                }
                
                $default = CompanyDefaultAccount::updateOrCreate(
                    [
                        'setting_name' => $defaultData['setting_name'],
                        'team_id' => currentTeamId(),
                    ],
                    [
                        'account_id' => $defaultData['account_id'],
                    ]
                );
                
                $results[] = $default;
            } catch (\Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'setting_name' => $defaultData['setting_name'],
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $this->success([
            'updated' => $results,
            'errors' => $errors,
            'summary' => [
                'total' => count($validated['defaults']),
                'updated' => count($results),
                'failed' => count($errors),
            ],
        ], 'Bulk update completed');
    }
}
