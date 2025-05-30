<?php

namespace Condoedge\Finance\Database\Seeders\GL;

use Illuminate\Database\Seeder;
use Condoedge\Finance\Models\GL\FiscalYearSetup;
use Condoedge\Finance\Models\GL\FiscalPeriod;
use Condoedge\Finance\Models\GL\AccountSegmentDefinition;
use Condoedge\Finance\Models\GL\GlSegmentValue;
use Condoedge\Finance\Models\GL\CompanyDefaultAccount;
use Condoedge\Finance\Services\GL\FiscalPeriodService;
use Carbon\Carbon;

class GlSetupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $this->setupFiscalYear();
        $this->setupAccountStructure();
        $this->setupDefaultAccounts();
    }

    protected function setupFiscalYear()
    {
        // Create fiscal year setup
        $fiscalSetup = FiscalYearSetup::create([
            'company_fiscal_start_date' => Carbon::parse('2024-05-01'),
            'is_active' => true,
            'notes' => 'Default fiscal year setup - May 1st start date'
        ]);

        // Create fiscal periods
        $fiscalPeriodService = new FiscalPeriodService();
        $fiscalPeriodService->createFiscalPeriods(
            2025, // Fiscal year
            Carbon::parse('2024-05-01'), // Start date
            12 // Number of periods
        );

        $this->command->info('Fiscal year setup completed');
    }

    protected function setupAccountStructure()
    {
        // Define account structure: XX-XXX-XXXX
        // Segment 1: Project (2 chars)
        // Segment 2: Activity (3 chars) 
        // Segment 3: Natural Account (4 chars)

        $segments = [
            [
                'position' => 1,
                'length' => 2,
                'name' => 'Project',
                'description' => 'Project or division identifier'
            ],
            [
                'position' => 2,
                'length' => 3,
                'name' => 'Activity',
                'description' => 'Activity or department code'
            ],
            [
                'position' => 3,
                'length' => 4,
                'name' => 'Account',
                'description' => 'Natural account classification'
            ]
        ];

        foreach ($segments as $segment) {
            // Create segment definition
            AccountSegmentDefinition::create([
                'segment_position' => $segment['position'],
                'segment_length' => $segment['length'],
                'segment_name' => $segment['name'],
                'segment_description' => $segment['description'],
                'is_active' => true
            ]);

            // Create structure definition in segment values
            GlSegmentValue::create([
                'segment_type' => GlSegmentValue::TYPE_STRUCTURE_DEFINITION,
                'segment_number' => $segment['position'],
                'segment_value' => null,
                'segment_description' => $segment['name'],
                'is_active' => true
            ]);
        }

        // Create sample segment values
        $this->createSegmentValues();

        $this->command->info('Account structure setup completed');
    }

    protected function createSegmentValues()
    {
        // Segment 1 values (Project)
        $projects = [
            ['04', 'Project Alpha'],
            ['05', 'Project Beta'],
            ['10', 'General Operations'],
            ['99', 'Corporate']
        ];

        foreach ($projects as [$value, $description]) {
            GlSegmentValue::create([
                'segment_type' => GlSegmentValue::TYPE_SEGMENT_VALUE,
                'segment_number' => 1,
                'segment_value' => $value,
                'segment_description' => $description,
                'is_active' => true
            ]);
        }

        // Segment 2 values (Activity)
        $activities = [
            ['205', 'Construction'],
            ['405', 'Operations'],
            ['505', 'Administration'],
            ['605', 'Sales & Marketing'],
            ['705', 'Finance']
        ];

        foreach ($activities as [$value, $description]) {
            GlSegmentValue::create([
                'segment_type' => GlSegmentValue::TYPE_SEGMENT_VALUE,
                'segment_number' => 2,
                'segment_value' => $value,
                'segment_description' => $description,
                'is_active' => true
            ]);
        }

        // Segment 3 values (Natural Accounts)
        $accounts = [
            // Assets (1000-1999)
            ['1105', 'Cash - Operating'],
            ['1110', 'Cash - Savings'],
            ['1200', 'Accounts Receivable'],
            ['1300', 'Inventory'],
            ['1500', 'Equipment'],
            ['1600', 'Accumulated Depreciation'],
            
            // Liabilities (2000-2999)
            ['2100', 'Accounts Payable'],
            ['2200', 'Accrued Expenses'],
            ['2300', 'Notes Payable'],
            ['2400', 'Long-term Debt'],
            
            // Equity (3000-3999)
            ['3100', 'Owner\'s Equity'],
            ['3200', 'Retained Earnings'],
            
            // Revenue (4000-4999)
            ['4100', 'Sales Revenue'],
            ['4200', 'Service Revenue'],
            ['4300', 'Other Income'],
            
            // Expenses (5000-9999)
            ['5100', 'Cost of Goods Sold'],
            ['5200', 'Material Expenses'],
            ['5300', 'Labor Expenses'],
            ['6100', 'Rent Expense'],
            ['6200', 'Utilities Expense'],
            ['6300', 'Insurance Expense'],
            ['7100', 'Salaries Expense'],
            ['7200', 'Benefits Expense'],
            ['8100', 'Depreciation Expense'],
            ['8200', 'Interest Expense'],
            ['9100', 'Office Supplies'],
            ['9200', 'Travel Expense']
        ];

        foreach ($accounts as [$value, $description]) {
            GlSegmentValue::create([
                'segment_type' => GlSegmentValue::TYPE_SEGMENT_VALUE,
                'segment_number' => 3,
                'segment_value' => $value,
                'segment_description' => $description,
                'is_active' => true
            ]);
        }
    }

    protected function setupDefaultAccounts()
    {
        $defaults = [
            [CompanyDefaultAccount::DEFAULT_REVENUE_ACCOUNT, '10-505-4100', 'Default Revenue Account'],
            [CompanyDefaultAccount::DEFAULT_EXPENSE_ACCOUNT, '10-505-9100', 'Default Expense Account'],
            [CompanyDefaultAccount::DEFAULT_BANK_ACCOUNT, '10-705-1105', 'Default Bank Account'],
            [CompanyDefaultAccount::DEFAULT_ACCOUNTS_PAYABLE, '10-705-2100', 'Default Accounts Payable'],
            [CompanyDefaultAccount::DEFAULT_ACCOUNTS_RECEIVABLE, '10-705-1200', 'Default Accounts Receivable'],
            [CompanyDefaultAccount::DEFAULT_COGS_ACCOUNT, '10-505-5100', 'Default Cost of Goods Sold'],
            [CompanyDefaultAccount::DEFAULT_RETAINED_EARNINGS, '10-705-3200', 'Default Retained Earnings']
        ];

        foreach ($defaults as [$settingName, $accountId, $description]) {
            CompanyDefaultAccount::create([
                'setting_name' => $settingName,
                'account_id' => $accountId,
                'description' => $description,
                'is_active' => true
            ]);
        }

        $this->command->info('Default accounts setup completed');
    }
}
