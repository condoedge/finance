<?php

namespace Tests\Unit;

use Condoedge\Finance\Enums\GlTransactionTypeEnum;
use Condoedge\Finance\Models\FiscalPeriod;
use Condoedge\Finance\Models\GlTransactionHeader;
use Condoedge\Finance\Services\FiscalYearService;
use Exception;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Kompo\Auth\Database\Factories\TeamFactory;
use Kompo\Auth\Database\Factories\UserFactory;
use Tests\TestCase;

class FiscalPeriodSystemTest extends TestCase
{
    use WithFaker;

    protected FiscalYearService $fiscalService;

    public function setUp(): void
    {
        parent::setUp();

        /** @var \Kompo\Auth\Models\User $user */
        $user = UserFactory::new()->create()->first();
        if (!$user) {
            throw new Exception('Unknown error creating user');
        }
        $this->actingAs($user);

        $this->fiscalService = app(FiscalYearService::class);

        // Clean up any existing fiscal setup
        DB::table('fin_fiscal_periods')->delete();
        DB::table('fin_fiscal_year_setup')->delete();
    }

    /**
     * Test fiscal year calculation is start year + 1
     */
    public function test_fiscal_year_calculation_is_start_plus_one()
    {
        // Setup fiscal year starting May 1, 2024
        $startDate = Carbon::parse('2024-05-01');
        $setup = $this->fiscalService->setupFiscalYear(currentTeamId(), $startDate);

        // Verify setup was created
        $this->assertDatabaseHas('fin_fiscal_year_setup', [
            'team_id' => currentTeamId(),
            'fiscal_start_date' => '2024-05-01',
        ]);

        // Test various dates and their fiscal year calculation
        $testCases = [
            // Dates in FY 2025 (May 2024 - April 2025)
            ['date' => '2024-05-01', 'expected_fy' => 2025], // Start of fiscal year
            ['date' => '2024-12-31', 'expected_fy' => 2025], // End of calendar year
            ['date' => '2025-01-15', 'expected_fy' => 2025], // Middle of fiscal year
            ['date' => '2025-04-30', 'expected_fy' => 2025], // End of fiscal year

            // Dates in FY 2024 (would be May 2023 - April 2024)
            ['date' => '2024-04-30', 'expected_fy' => 2024], // Day before FY 2025 starts
            ['date' => '2024-01-01', 'expected_fy' => 2024], // Earlier in calendar year
        ];

        foreach ($testCases as $test) {
            $calculatedFY = $this->fiscalService->getFiscalYearForDate(
                Carbon::parse($test['date']),
                currentTeamId()
            );

            $this->assertEquals(
                $test['expected_fy'],
                $calculatedFY,
                "Date {$test['date']} should be in FY {$test['expected_fy']}"
            );
        }
    }

    /**
     * Test that only current month auto-creates periods
     */
    public function test_only_current_month_auto_creates()
    {
        // Setup fiscal year
        $this->fiscalService->setupFiscalYear(currentTeamId(), Carbon::parse('2024-05-01'));

        // Try to get period for current month (should auto-create)
        $currentPeriod = $this->fiscalService->getOrCreatePeriodForDate(
            currentTeamId(),
            now(),
            true // onlyCurrentMonth = true
        );

        $this->assertNotNull($currentPeriod);
        $this->assertTrue($currentPeriod->is_open_gl);
        $this->assertTrue($currentPeriod->is_open_bnk);
        $this->assertTrue($currentPeriod->is_open_rm);
        $this->assertTrue($currentPeriod->is_open_pm);

        // Try to get period for next month (should fail)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(__('error-with-values-period-does-not-exist-just-can-create-for-current-month'));

        $this->fiscalService->getOrCreatePeriodForDate(
            currentTeamId(),
            now()->addMonth(),
            true // onlyCurrentMonth = true
        );
    }

    /**
     * Test module toggles work independently
     */
    public function test_module_toggles_work_independently()
    {
        // Setup and create current period
        $this->fiscalService->setupFiscalYear(currentTeamId(), Carbon::parse('2024-05-01'));
        $period = FiscalPeriod::getPeriodFromDate(now(), currentTeamId());

        // Initially all should be open
        $this->assertTrue($period->is_open_gl);
        $this->assertTrue($period->is_open_bnk);
        $this->assertTrue($period->is_open_rm);
        $this->assertTrue($period->is_open_pm);

        // Close only GL module
        $period->closeForModule(GlTransactionTypeEnum::MANUAL_GL);
        $period->refresh();

        $this->assertFalse($period->is_open_gl);
        $this->assertTrue($period->is_open_bnk);
        $this->assertTrue($period->is_open_rm);
        $this->assertTrue($period->is_open_pm);

        // Close BNK module
        $period->closeForModule(GlTransactionTypeEnum::BANK);
        $period->refresh();

        $this->assertFalse($period->is_open_gl);
        $this->assertFalse($period->is_open_bnk);
        $this->assertTrue($period->is_open_rm);
        $this->assertTrue($period->is_open_pm);

        // Reopen GL module
        $period->is_open_gl = true;
        $period->save();
        $period->refresh();

        $this->assertTrue($period->is_open_gl);
        $this->assertFalse($period->is_open_bnk);
        $this->assertTrue($period->is_open_rm);
        $this->assertTrue($period->is_open_pm);
    }

    /**
     * Test closed period prevents transactions
     */
    public function test_closed_period_prevents_transactions()
    {
        // Setup fiscal year and period
        $this->fiscalService->setupFiscalYear(currentTeamId(), Carbon::parse('2024-05-01'));
        $period = FiscalPeriod::getPeriodFromDate(now(), currentTeamId());

        // Close GL module
        $period->is_open_gl = false;
        $period->save();

        // Try to create GL transaction
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(__('finance-fiscal-year-period-closed'));

        $transaction = new GlTransactionHeader();
        $transaction->fiscal_date = now();
        $transaction->gl_transaction_type = GlTransactionTypeEnum::MANUAL_GL->value;
        $transaction->transaction_description = 'Test transaction';
        $transaction->team_id = currentTeamId();
        $transaction->save(); // ValidatesFiscalPeriod trait should prevent this
    }

    /**
     * Test trait validates automatically on model creation
     */
    public function test_trait_validates_automatically()
    {
        // Setup fiscal year
        $this->fiscalService->setupFiscalYear(currentTeamId(), Carbon::parse('2024-05-01'));

        // Don't create period for next month
        $nextMonth = now()->addMonth();

        // Try to create transaction for next month
        $this->expectException(InvalidArgumentException::class);

        $transaction = new GlTransactionHeader();
        $transaction->fiscal_date = $nextMonth;
        $transaction->gl_transaction_type = GlTransactionTypeEnum::MANUAL_GL->value;
        $transaction->transaction_description = 'Future transaction';
        $transaction->team_id = currentTeamId();
        $transaction->save(); // Should fail - no period exists
    }

    /**
     * Test pre-create command works correctly
     */
    public function test_pre_create_command_works()
    {
        // Setup fiscal year
        $this->fiscalService->setupFiscalYear(currentTeamId(), Carbon::parse('2024-05-01'));

        // Creating for current month
        $currentPeriod = $this->fiscalService->getOrCreatePeriodForDate(
            currentTeamId(),
            now(),
            true // onlyCurrentMonth = true
        );

        $this->assertTrue($currentPeriod->is_open_gl);
        $this->assertTrue($currentPeriod->is_open_bnk);
        $this->assertTrue($currentPeriod->is_open_rm);
        $this->assertTrue($currentPeriod->is_open_pm);

        // Verify next month period doesn't exist
        $nextMonth = now()->addMonth()->startOfMonth();
        $period = FiscalPeriod::getPeriodFromDate($nextMonth, currentTeamId());
        $this->assertNull($period);

        // Run pre-create command
        Artisan::call('finance:pre-create-periods', [
            '--days-ahead' => now()->addMonth()->daysInMonth + 1,
        ]);

        // Verify period was created
        $period = FiscalPeriod::getPeriodFromDate($nextMonth, currentTeamId());
        $this->assertNotNull($period);

        // Should be created opened
        $this->assertTrue($period->is_open_gl);
        $this->assertTrue($period->is_open_bnk);
        $this->assertTrue($period->is_open_rm);
        $this->assertTrue($period->is_open_pm);

        // Should have closed the previous month
        $previousMonth = now()->startOfMonth();
        $previousPeriod = FiscalPeriod::getPeriodFromDate($previousMonth, currentTeamId());
        $this->assertFalse($previousPeriod->is_open_gl);
        $this->assertFalse($previousPeriod->is_open_bnk);
        $this->assertFalse($previousPeriod->is_open_rm);
        $this->assertFalse($previousPeriod->is_open_pm);
    }

    /**
     * Test period numbering is correct
     */
    public function test_period_numbering_is_correct()
    {
        // Setup fiscal year starting May 1
        $this->fiscalService->setupFiscalYear(currentTeamId(), Carbon::parse('2024-05-01'));

        // Create periods for entire fiscal year
        $this->fiscalService->createPeriodsUpToDate(
            currentTeamId(),
            Carbon::parse('2024-05-01'),
            Carbon::parse('2025-04-30')
        );

        // Verify period numbers
        $expectedMapping = [
            '2024-05' => 1,  // May is period 1
            '2024-06' => 2,  // June is period 2
            '2024-07' => 3,
            '2024-08' => 4,
            '2024-09' => 5,
            '2024-10' => 6,
            '2024-11' => 7,
            '2024-12' => 8,
            '2025-01' => 9,  // January is period 9
            '2025-02' => 10,
            '2025-03' => 11,
            '2025-04' => 12, // April is period 12
        ];

        foreach ($expectedMapping as $yearMonth => $expectedPeriod) {
            $date = Carbon::parse($yearMonth . '-15');
            $period = FiscalPeriod::getPeriodFromDate($date, currentTeamId());

            $this->assertNotNull($period, "Period should exist for {$yearMonth}");
            $this->assertEquals(
                $expectedPeriod,
                $period->period_number,
                "Period number for {$yearMonth} should be {$expectedPeriod}"
            );
        }
    }

    /**
     * Test fiscal year setup changes clean up old periods
     */
    public function test_fiscal_year_change_cleans_old_periods()
    {
        // Initial setup with May start
        $this->fiscalService->setupFiscalYear(currentTeamId(), Carbon::parse('2024-05-01'));

        // Create some periods
        $this->fiscalService->createPeriodsUpToDate(
            currentTeamId(),
            Carbon::parse('2024-05-01'),
            Carbon::parse('2024-08-31')
        );

        $initialCount = FiscalPeriod::where('team_id', currentTeamId())->count();
        $this->assertGreaterThan(0, $initialCount);

        // Change fiscal year to January start
        $this->fiscalService->setupFiscalYear(currentTeamId(), Carbon::parse('2025-01-01'));

        // Old periods should be cleaned up
        $oldPeriods = FiscalPeriod::where('team_id', currentTeamId())
            ->whereDate('start_date', '<', '2025-01-01')
            ->count();

        $this->assertEquals(0, $oldPeriods, 'Old periods should be cleaned up');
    }

    /**
     * Test period date ranges are correct
     */
    public function test_period_date_ranges_are_correct()
    {
        $this->fiscalService->setupFiscalYear(currentTeamId(), Carbon::parse('2024-05-01'));

        // Create a few periods
        $this->fiscalService->createPeriodsUpToDate(
            currentTeamId(),
            Carbon::parse('2024-05-01'),
            Carbon::parse('2024-07-31')
        );

        // May period
        $mayPeriod = FiscalPeriod::getPeriodFromDate(Carbon::parse('2024-05-15'), currentTeamId());
        $this->assertEquals('2024-05-01', $mayPeriod->start_date->format('Y-m-d'));
        $this->assertEquals('2024-05-31', $mayPeriod->end_date->format('Y-m-d'));

        // June period
        $junePeriod = FiscalPeriod::getPeriodFromDate(Carbon::parse('2024-06-15'), currentTeamId());
        $this->assertEquals('2024-06-01', $junePeriod->start_date->format('Y-m-d'));
        $this->assertEquals('2024-06-30', $junePeriod->end_date->format('Y-m-d'));

        // July period
        $julyPeriod = FiscalPeriod::getPeriodFromDate(Carbon::parse('2024-07-15'), currentTeamId());
        $this->assertEquals('2024-07-01', $julyPeriod->start_date->format('Y-m-d'));
        $this->assertEquals('2024-07-31', $julyPeriod->end_date->format('Y-m-d'));
    }

    /**
     * Test multi-tenant isolation
     */
    public function test_multi_tenant_isolation()
    {
        $team2 = TeamFactory::new()->create();

        // Setup fiscal year for team 1
        $this->fiscalService->setupFiscalYear(currentTeamId(), Carbon::parse('2024-05-01'));

        // Setup different fiscal year for team 2
        $this->fiscalService->setupFiscalYear($team2->id, Carbon::parse('2024-01-01'));

        // Create periods for both teams
        $team1Period = $this->fiscalService->getOrCreatePeriodForDate(currentTeamId(), now());
        $team2Period = $this->fiscalService->getOrCreatePeriodForDate($team2->id, now());

        // Verify isolation
        $this->assertNotEquals($team1Period->id, $team2Period->id);

        // Verify fiscal years are different
        $team1FY = $this->fiscalService->getCurrentFiscalYear(currentTeamId());
        $team2FY = $this->fiscalService->getCurrentFiscalYear($team2->id);

        $this->assertEquals(now()->year + 1, $team1FY);
        $this->assertEquals(now()->year + 1, $team2FY);

        // But period numbers should be different
        $this->assertNotEquals($team1Period->period_number, $team2Period->period_number);
    }

    /**
     * Test edge case: transaction at exact period boundary
     */
    public function test_transaction_at_period_boundary()
    {
        $this->fiscalService->setupFiscalYear(currentTeamId(), Carbon::parse('2024-05-01'));

        // Create May and June periods
        $this->fiscalService->createPeriodsUpToDate(
            currentTeamId(),
            Carbon::parse('2024-05-01'),
            Carbon::parse('2024-06-30')
        );

        // Transaction on last day of May
        $lastDayMay = Carbon::parse('2024-05-31');
        $mayPeriod = FiscalPeriod::getPeriodFromDate($lastDayMay, currentTeamId());
        $this->assertEquals(1, $mayPeriod->period_number);

        // Transaction on first day of June
        $firstDayJune = Carbon::parse('2024-06-01');
        $junePeriod = FiscalPeriod::getPeriodFromDate($firstDayJune, currentTeamId());
        $this->assertEquals(2, $junePeriod->period_number);
    }

    /**
     * Test period display format
     */
    public function test_period_display_format()
    {
        $this->fiscalService->setupFiscalYear(currentTeamId(), Carbon::parse('2024-05-01'));
        $period = $this->fiscalService->getOrCreatePeriodForDate(currentTeamId(), now());

        // Get period display info
        $periodInfo = $this->fiscalService->getPeriodsForFiscalYear(currentTeamId(), $period->fiscal_year);
        $currentPeriodInfo = collect($periodInfo)->firstWhere('period.id', $period->id);

        $this->assertNotNull($currentPeriodInfo);
        $this->assertArrayHasKey('period_display', $currentPeriodInfo);

        // Format should be like "per09-2025 from 2025-01-01 to 2025-01-31"
        $this->assertStringContainsString('per' . $period->id, $currentPeriodInfo['period_display']);
        $this->assertStringContainsString('2025', $currentPeriodInfo['period_display']);
        $this->assertStringContainsString('from', $currentPeriodInfo['period_display']);
        $this->assertStringContainsString('to', $currentPeriodInfo['period_display']);
    }

    /**
     * Test closing expired periods
     */
    public function test_closing_expired_periods()
    {
        $this->fiscalService->setupFiscalYear(currentTeamId(), Carbon::parse('2024-05-01'));

        // Create past period that should be closed
        $pastDate = now()->subMonth();
        $pastPeriod = $this->fiscalService->getOrCreatePeriodForDate(currentTeamId(), $pastDate, false);

        // Make sure it's open
        $pastPeriod->is_open_gl = true;
        $pastPeriod->is_open_bnk = true;
        $pastPeriod->is_open_rm = true;
        $pastPeriod->is_open_pm = true;
        $pastPeriod->save();

        // Close expired periods
        $this->fiscalService->closeExpiredPeriods(currentTeamId());

        // Verify past period is now closed
        $pastPeriod->refresh();
        $this->assertFalse($pastPeriod->is_open_gl);
        $this->assertFalse($pastPeriod->is_open_bnk);
        $this->assertFalse($pastPeriod->is_open_rm);
        $this->assertFalse($pastPeriod->is_open_pm);

        // Current period should still be open
        $currentPeriod = FiscalPeriod::getPeriodFromDate(now(), currentTeamId());
        $this->assertTrue($currentPeriod->is_open_gl);
    }

    /**
     * Test dry run mode of pre-create command
     */
    public function test_pre_create_command_dry_run()
    {
        $this->fiscalService->setupFiscalYear(currentTeamId(), Carbon::parse('2024-05-01'));

        $nextMonth = now()->addMonth()->startOfMonth();

        // Run in dry-run mode
        Artisan::call('finance:pre-create-periods', [
            '--days-ahead' => 1,
            '--dry-run' => true,
        ]);

        // Period should NOT be created in dry-run
        $period = FiscalPeriod::getPeriodFromDate($nextMonth, currentTeamId());
        $this->assertNull($period);
    }

    /**
     * Test concurrent period creation handles race conditions
     */
    public function test_concurrent_period_creation()
    {
        $this->fiscalService->setupFiscalYear(currentTeamId(), Carbon::parse('2024-05-01'));

        $date = now();
        $results = [];

        // Simulate concurrent requests trying to create same period
        for ($i = 0; $i < 5; $i++) {
            try {
                $period = $this->fiscalService->getOrCreatePeriodForDate(currentTeamId(), $date);
                $results[] = $period->id;
            } catch (\Exception $e) {
                // Duplicate key errors are expected in race conditions
            }
        }

        // Should only create one period despite concurrent attempts
        $uniquePeriods = array_unique($results);
        $this->assertCount(1, $uniquePeriods);

        // Verify only one period exists in database
        $count = FiscalPeriod::where('team_id', currentTeamId())
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->count();

        $this->assertEquals(1, $count);
    }
}
