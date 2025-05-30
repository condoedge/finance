<?php

namespace Condoedge\Finance\Database\Seeders\GL;

use Illuminate\Database\Seeder;
use Condoedge\Finance\Models\GL\GlAccount;
use Condoedge\Finance\Services\GL\ChartOfAccountsService;

class ChartOfAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $this->createSampleAccounts();
    }

    protected function createSampleAccounts()
    {
        $chartOfAccountsService = new ChartOfAccountsService();

        $accounts = [
            // Cash and Bank Accounts
            [
                'segments' => ['10', '705', '1105'],
                'description' => 'Operating Cash Account',
                'account_type' => 'Asset',
                'account_category' => 'Current Assets'
            ],
            [
                'segments' => ['10', '705', '1110'],
                'description' => 'Savings Account',
                'account_type' => 'Asset',
                'account_category' => 'Current Assets'
            ],

            // Receivables
            [
                'segments' => ['10', '705', '1200'],
                'description' => 'Accounts Receivable - Trade',
                'account_type' => 'Asset',
                'account_category' => 'Current Assets'
            ],

            // Inventory
            [
                'segments' => ['10', '505', '1300'],
                'description' => 'Raw Materials Inventory',
                'account_type' => 'Asset',
                'account_category' => 'Current Assets'
            ],

            // Fixed Assets
            [
                'segments' => ['10', '505', '1500'],
                'description' => 'Equipment and Machinery',
                'account_type' => 'Asset',
                'account_category' => 'Fixed Assets'
            ],
            [
                'segments' => ['10', '505', '1600'],
                'description' => 'Accumulated Depreciation - Equipment',
                'account_type' => 'Asset',
                'account_category' => 'Fixed Assets'
            ],

            // Payables
            [
                'segments' => ['10', '705', '2100'],
                'description' => 'Accounts Payable - Trade',
                'account_type' => 'Liability',
                'account_category' => 'Current Liabilities'
            ],
            [
                'segments' => ['10', '705', '2200'],
                'description' => 'Accrued Expenses',
                'account_type' => 'Liability',
                'account_category' => 'Current Liabilities'
            ],

            // Long-term Debt
            [
                'segments' => ['10', '705', '2400'],
                'description' => 'Long-term Notes Payable',
                'account_type' => 'Liability',
                'account_category' => 'Long-term Liabilities'
            ],

            // Equity
            [
                'segments' => ['10', '705', '3100'],
                'description' => 'Owner\'s Equity',
                'account_type' => 'Equity',
                'account_category' => 'Owner\'s Equity'
            ],
            [
                'segments' => ['10', '705', '3200'],
                'description' => 'Retained Earnings',
                'account_type' => 'Equity',
                'account_category' => 'Retained Earnings'
            ],

            // Revenue Accounts
            [
                'segments' => ['04', '205', '4100'],
                'description' => 'Construction Revenue - Project Alpha',
                'account_type' => 'Revenue',
                'account_category' => 'Operating Revenue'
            ],
            [
                'segments' => ['05', '205', '4100'],
                'description' => 'Construction Revenue - Project Beta',
                'account_type' => 'Revenue',
                'account_category' => 'Operating Revenue'
            ],
            [
                'segments' => ['10', '605', '4200'],
                'description' => 'Service Revenue',
                'account_type' => 'Revenue',
                'account_category' => 'Operating Revenue'
            ],

            // Cost of Goods Sold
            [
                'segments' => ['04', '205', '5100'],
                'description' => 'Cost of Goods Sold - Project Alpha',
                'account_type' => 'Expense',
                'account_category' => 'Cost of Sales'
            ],
            [
                'segments' => ['04', '205', '5200'],
                'description' => 'Material Expenses - Project Alpha',
                'account_type' => 'Expense',
                'account_category' => 'Direct Costs'
            ],
            [
                'segments' => ['05', '405', '5200'],
                'description' => 'Material Expenses - Project Beta',
                'account_type' => 'Expense',
                'account_category' => 'Direct Costs'
            ],

            // Operating Expenses
            [
                'segments' => ['10', '505', '6100'],
                'description' => 'Rent Expense - Administration',
                'account_type' => 'Expense',
                'account_category' => 'Operating Expenses'
            ],
            [
                'segments' => ['10', '505', '6200'],
                'description' => 'Utilities Expense',
                'account_type' => 'Expense',
                'account_category' => 'Operating Expenses'
            ],
            [
                'segments' => ['10', '605', '7100'],
                'description' => 'Sales Salaries Expense',
                'account_type' => 'Expense',
                'account_category' => 'Personnel Expenses'
            ],
            [
                'segments' => ['10', '505', '7100'],
                'description' => 'Administrative Salaries Expense',
                'account_type' => 'Expense',
                'account_category' => 'Personnel Expenses'
            ],
            [
                'segments' => ['10', '705', '8100'],
                'description' => 'Depreciation Expense',
                'account_type' => 'Expense',
                'account_category' => 'Non-Cash Expenses'
            ],
            [
                'segments' => ['10', '705', '8200'],
                'description' => 'Interest Expense',
                'account_type' => 'Expense',
                'account_category' => 'Financial Expenses'
            ],

            // General Expenses
            [
                'segments' => ['10', '505', '9100'],
                'description' => 'Office Supplies Expense',
                'account_type' => 'Expense',
                'account_category' => 'General Expenses'
            ],
            [
                'segments' => ['10', '605', '9200'],
                'description' => 'Travel and Entertainment',
                'account_type' => 'Expense',
                'account_category' => 'General Expenses'
            ]
        ];

        foreach ($accounts as $accountData) {
            try {
                $account = $chartOfAccountsService->createGlAccount(
                    $accountData['segments'],
                    $accountData['description'],
                    $accountData['account_type'],
                    $accountData['account_category']
                );
                
                $this->command->info("Created account: {$account->account_id} - {$account->account_description}");
            } catch (\Exception $e) {
                $this->command->error("Failed to create account " . implode('-', $accountData['segments']) . ": " . $e->getMessage());
            }
        }

        $this->command->info('Chart of accounts seeding completed');
    }
}
