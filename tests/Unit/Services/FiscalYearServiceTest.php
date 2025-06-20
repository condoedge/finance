<?php

namespace Tests\Unit\Services;

use Condoedge\Finance\Services\FiscalYearService;
use Condoedge\Finance\Models\FiscalYearSetup;
use Condoedge\Finance\Models\FiscalPeriod;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Test the fiscal year and period management system
 */
class FiscalYearServiceTest extends TestCase
{
    use RefreshDatabase;
    
    protected FiscalYearService $fiscalService;
    protected int $teamId = 1;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->fiscalService = app(FiscalYearService::class);
    }
    
    #[Test]
    public function it_can_setup_fiscal_year()
    {
        $startDate = Carbon::parse('2024-05-01');
        
        $setup = $this->fiscalService->setupFiscalYear($this->teamId, $startDate);
        
        $this->assertInstanceOf(FiscalYearSetup::class, $setup);
        $this->assertEquals($this->teamId, $setup->team_id);
        $this->assertEquals('2024-05-01', $setup->company_fiscal_start_date->format('Y-m-d'));
        $this->assertTrue($setup->is_active);
    }
    
    #[Test]
    public function it_calculates_fiscal_year_correctly()
    {
        // Rule: Fiscal year = start date year + 1
        $startDate = Carbon::parse('2024-05-01');
        $fiscalYear = $this->fiscalService->calculateFiscalYear($startDate);
        
        $this->assertEquals(2025, $fiscalYear);
        
        // Test with different year
        $startDate2 = Carbon::parse('2023-01-01');
        $fiscalYear2 = $this->fiscalService->calculateFiscalYear($startDate2);
        
        $this->assertEquals(2024, $fiscalYear2);
    }
    
    #[Test]
    public function it_determines_fiscal_year_for_any_date()
    {
        // Setup fiscal year starting 2024-05-01 (fiscal year 2025)
        $this->fiscalService->setupFiscalYear($this->teamId, Carbon::parse('2024-05-01'));
        
        // Date before fiscal start in same year should be previous fiscal year
        $date1 = Carbon::parse('2024-04-30');
        $fy1 = $this->fiscalService->getFiscalYearForDate($date1, $this->teamId);
        $this->assertEquals(2024, $fy1);
        
        // Date on fiscal start should be current fiscal year
        $date2 = Carbon::parse('2024-05-01');
        $fy2 = $this->fiscalService->getFiscalYearForDate($date2, $this->teamId);
        $this->assertEquals(2025, $fy2);
        
        // Date after fiscal start should be current fiscal year
        $date3 = Carbon::parse('2024-12-31');
        $fy3 = $this->fiscalService->getFiscalYearForDate($date3, $this->teamId);
        $this->assertEquals(2025, $fy3);
    }
    
    #[Test]
    public function it_can_generate_fiscal_periods()
    {
        // Setup fiscal year
        $this->fiscalService->setupFiscalYear($this->teamId, Carbon::parse('2024-05-01'));
        
        // Generate periods for fiscal year 2025
        $periods = $this->fiscalService->generateFiscalPeriods($this->teamId, 2025);
        
        $this->assertCount(12, $periods);
        
        // Check first period
        $firstPeriod = $periods[0];
        $this->assertEquals('per01-2025', $firstPeriod->period_id);
        $this->assertEquals('2024-05-01', $firstPeriod->start_date->format('Y-m-d'));
        $this->assertEquals('2024-05-31', $firstPeriod->end_date->format('Y-m-d'));
        $this->assertEquals(2025, $firstPeriod->fiscal_year);
        $this->assertEquals(1, $firstPeriod->period_number);
        
        // Check that all modules are initially open
        $this->assertTrue($firstPeriod->is_open_gl);
        $this->assertTrue($firstPeriod->is_open_bnk);
        $this->assertTrue($firstPeriod->is_open_rm);
        $this->assertTrue($firstPeriod->is_open_pm);
        
        // Check second period
        $secondPeriod = $periods[1];
        $this->assertEquals('per02-2025', $secondPeriod->period_id);
        $this->assertEquals('2024-06-01', $secondPeriod->start_date->format('Y-m-d'));
        $this->assertEquals('2024-06-30', $secondPeriod->end_date->format('Y-m-d'));
    }
    
    #[Test]
    public function it_prevents_duplicate_period_generation()
    {
        $this->fiscalService->setupFiscalYear($this->teamId, Carbon::parse('2024-05-01'));
        
        // Generate periods first time
        $periods1 = $this->fiscalService->generateFiscalPeriods($this->teamId, 2025);
        $this->assertCount(12, $periods1);
        
        // Try to generate again (should skip existing)
        $periods2 = $this->fiscalService->generateFiscalPeriods($this->teamId, 2025);
        $this->assertCount(0, $periods2); // No new periods created
        
        // Total periods should still be 12
        $totalPeriods = FiscalPeriod::where('team_id', $this->teamId)
            ->where('fiscal_year', 2025)
            ->count();
        $this->assertEquals(12, $totalPeriods);
    }
    
    #[Test]
    public function it_can_regenerate_periods_if_none_are_closed()
    {
        $this->fiscalService->setupFiscalYear($this->teamId, Carbon::parse('2024-05-01'));
        
        // Generate initial periods
        $this->fiscalService->generateFiscalPeriods($this->teamId, 2025);
        
        // Regenerate (should work since no periods are closed)
        $newPeriods = $this->fiscalService->generateFiscalPeriods($this->teamId, 2025, true);
        $this->assertCount(12, $newPeriods);
    }
    
    #[Test]
    public function it_prevents_regeneration_if_periods_are_closed()
    {
        $this->fiscalService->setupFiscalYear($this->teamId, Carbon::parse('2024-05-01'));
        $this->fiscalService->generateFiscalPeriods($this->teamId, 2025);
        
        // Close a period
        $this->fiscalService->closePeriod('per01-2025', ['GL']);
        
        // Try to regenerate (should fail)
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot regenerate periods: some periods are already closed');
        
        $this->fiscalService->generateFiscalPeriods($this->teamId, 2025, true);
    }
    
    #[Test]
    public function it_can_close_periods_for_specific_modules()
    {
        $this->fiscalService->setupFiscalYear($this->teamId, Carbon::parse('2024-05-01'));
        $this->fiscalService->generateFiscalPeriods($this->teamId, 2025);
        
        // Close GL and BNK modules for first period
        $closedPeriod = $this->fiscalService->closePeriod('per01-2025', ['GL', 'BNK']);
        
        $this->assertFalse($closedPeriod->is_open_gl);
        $this->assertFalse($closedPeriod->is_open_bnk);
        $this->assertTrue($closedPeriod->is_open_rm);  // Still open
        $this->assertTrue($closedPeriod->is_open_pm);  // Still open
    }
    
    #[Test]
    public function it_can_open_periods_for_specific_modules()
    {
        $this->fiscalService->setupFiscalYear($this->teamId, Carbon::parse('2024-05-01'));
        $this->fiscalService->generateFiscalPeriods($this->teamId, 2025);
        
        // Close all modules
        $this->fiscalService->closePeriod('per01-2025', ['GL', 'BNK', 'RM', 'PM']);
        
        // Open only GL and RM
        $openedPeriod = $this->fiscalService->openPeriod('per01-2025', ['GL', 'RM']);
        
        $this->assertTrue($openedPeriod->is_open_gl);   // Opened
        $this->assertFalse($openedPeriod->is_open_bnk); // Still closed
        $this->assertTrue($openedPeriod->is_open_rm);   // Opened
        $this->assertFalse($openedPeriod->is_open_pm);  // Still closed
    }
    
    #[Test]
    public function it_can_create_custom_periods()
    {
        $this->fiscalService->setupFiscalYear($this->teamId, Carbon::parse('2024-05-01'));
        
        $customPeriod = $this->fiscalService->createCustomPeriod(
            $this->teamId,
            2025,
            'qtr1',
            Carbon::parse('2024-05-01'),
            Carbon::parse('2024-07-31'),
            'Q1 Custom Period'
        );
        
        $this->assertEquals('qtr1-2025', $customPeriod->period_id);
        $this->assertEquals('2024-05-01', $customPeriod->start_date->format('Y-m-d'));
        $this->assertEquals('2024-07-31', $customPeriod->end_date->format('Y-m-d'));
        $this->assertEquals(0, $customPeriod->period_number); // Custom periods have 0
    }
    
    #[Test]
    public function it_validates_transaction_dates_against_closed_periods()
    {
        $this->fiscalService->setupFiscalYear($this->teamId, Carbon::parse('2024-05-01'));
        $this->fiscalService->generateFiscalPeriods($this->teamId, 2025);
        
        // Transaction in open period should be valid
        $this->assertTrue(
            $this->fiscalService->validateTransactionDate(
                Carbon::parse('2024-05-15'), 
                'GL', 
                $this->teamId
            )
        );
        
        // Close the period
        $this->fiscalService->closePeriod('per01-2025', ['GL']);
        
        // Transaction in closed period should fail
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('is closed for GL module');
        
        $this->fiscalService->validateTransactionDate(
            Carbon::parse('2024-05-15'), 
            'GL', 
            $this->teamId
        );
    }
    
    #[Test]
    public function it_can_get_current_period()
    {
        $this->fiscalService->setupFiscalYear($this->teamId, Carbon::parse('2024-05-01'));
        $this->fiscalService->generateFiscalPeriods($this->teamId, 2025);
        
        // Get current period for a date in first period
        $currentPeriod = $this->fiscalService->getCurrentPeriod(
            $this->teamId, 
            Carbon::parse('2024-05-15')
        );
        
        $this->assertNotNull($currentPeriod);
        $this->assertEquals('per01-2025', $currentPeriod->period_id);
        
        // Get current period for a date in second period
        $currentPeriod2 = $this->fiscalService->getCurrentPeriod(
            $this->teamId, 
            Carbon::parse('2024-06-15')
        );
        
        $this->assertNotNull($currentPeriod2);
        $this->assertEquals('per02-2025', $currentPeriod2->period_id);
    }
    
    #[Test]
    public function it_can_get_fiscal_year_summary()
    {
        $this->fiscalService->setupFiscalYear($this->teamId, Carbon::parse('2024-05-01'));
        $this->fiscalService->generateFiscalPeriods($this->teamId, 2025);
        
        // Close some periods
        $this->fiscalService->closePeriod('per01-2025', ['GL', 'BNK']);
        $this->fiscalService->closePeriod('per02-2025', ['GL']);
        
        $summary = $this->fiscalService->getFiscalYearSummary($this->teamId, 2025);
        
        $this->assertEquals(2025, $summary['fiscal_year']);
        $this->assertEquals(12, $summary['total_periods']);
        $this->assertEquals('2024-05-01', $summary['fiscal_start_date']->format('Y-m-d'));
        $this->assertEquals('2025-04-30', $summary['fiscal_end_date']->format('Y-m-d'));
        
        // Check closure status
        $this->assertFalse($summary['closure_status']['GL']);  // Not all GL periods closed
        $this->assertFalse($summary['closure_status']['BNK']); // Not all BNK periods closed
        $this->assertFalse($summary['closure_status']['RM']);  // No RM periods closed
        $this->assertFalse($summary['closure_status']['PM']);  // No PM periods closed
    }
    
    #[Test]
    public function it_detects_fully_closed_fiscal_year()
    {
        $this->fiscalService->setupFiscalYear($this->teamId, Carbon::parse('2024-05-01'));
        $this->fiscalService->generateFiscalPeriods($this->teamId, 2025);
        
        // Initially not closed
        $this->assertFalse($this->fiscalService->isFiscalYearClosed($this->teamId, 2025, 'GL'));
        
        // Close all periods for GL
        for ($i = 1; $i <= 12; $i++) {
            $periodId = sprintf('per%02d-2025', $i);
            $this->fiscalService->closePeriod($periodId, ['GL']);
        }
        
        // Now should be closed
        $this->assertTrue($this->fiscalService->isFiscalYearClosed($this->teamId, 2025, 'GL'));
        
        // But other modules should still be open
        $this->assertFalse($this->fiscalService->isFiscalYearClosed($this->teamId, 2025, 'BNK'));
    }
    
    #[Test]
    public function it_prevents_overlapping_custom_periods()
    {
        $this->fiscalService->setupFiscalYear($this->teamId, Carbon::parse('2024-05-01'));
        
        // Create first custom period
        $this->fiscalService->createCustomPeriod(
            $this->teamId,
            2025,
            'custom1',
            Carbon::parse('2024-05-01'),
            Carbon::parse('2024-05-31')
        );
        
        // Try to create overlapping period
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Period dates overlap with existing period');
        
        $this->fiscalService->createCustomPeriod(
            $this->teamId,
            2025,
            'custom2',
            Carbon::parse('2024-05-15'), // Overlaps with custom1
            Carbon::parse('2024-06-15')
        );
    }
}
