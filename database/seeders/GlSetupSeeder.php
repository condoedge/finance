<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Condoedge\Finance\Models\FiscalYearSetup;
use Condoedge\Finance\Models\FiscalPeriod;
use Carbon\Carbon;

class GlSetupSeeder extends Seeder
{
    public function run()
    {
        // Setup fiscal year (starts May 1st)
        FiscalYearSetup::create([
            'fiscal_start_date' => '2024-05-01',
            'is_active' => true,
        ]);
        
        // Create fiscal periods for FY2025 (May 2024 - Apr 2025)
        $periods = [
            ['per01', 1, '2024-05-01', '2024-05-31'],
            ['per02', 2, '2024-06-01', '2024-06-30'],
            ['per03', 3, '2024-07-01', '2024-07-31'],
            ['per04', 4, '2024-08-01', '2024-08-31'],
            ['per05', 5, '2024-09-01', '2024-09-30'],
            ['per06', 6, '2024-10-01', '2024-10-31'],
            ['per07', 7, '2024-11-01', '2024-11-30'],
            ['per08', 8, '2024-12-01', '2024-12-31'],
            ['per09', 9, '2025-01-01', '2025-01-31'],
            ['per10', 10, '2025-02-01', '2025-02-28'],
            ['per11', 11, '2025-03-01', '2025-03-31'],
            ['per12', 12, '2025-04-01', '2025-04-30'],
        ];
        
        foreach ($periods as [$periodId, $periodNumber, $startDate, $endDate]) {
            FiscalPeriod::create([
                'period_id' => $periodId,
                'fiscal_year' => 2025,
                'period_number' => $periodNumber,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_open_gl' => true,
                'is_open_bnk' => true,
                'is_open_rm' => true,
                'is_open_pm' => true,
            ]);
        }
    }
}
