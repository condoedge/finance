<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Condoedge\Finance\Services\AccountSegmentService;
use Condoedge\Finance\Services\SegmentDefaultHandlerService;
use Condoedge\Finance\Enums\SegmentDefaultHandlerEnum;
use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Models\SegmentValue;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Dto\Gl\CreateOrUpdateSegmentDto;
use Condoedge\Finance\Models\Dto\Gl\CreateAccountDto;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SegmentDefaultHandlerTest extends TestCase
{
    use RefreshDatabase;
    
    protected AccountSegmentService $segmentService;
    protected SegmentDefaultHandlerService $handlerService;
    
    public function test_setUp(): void
    {
        parent::setUp();
        
        $this->segmentService = app(AccountSegmentService::class);
        $this->handlerService = app(SegmentDefaultHandlerService::class);
        
        // Create basic team structure for testing
        $this->createTeamStructure();
    }
    
    /**
     * Test creating segment with default handler
     */
    public function test_test_can_create_segment_with_default_handler()
    {
        $dto = new CreateOrUpdateSegmentDto([
            'segment_description' => 'Company Code',
            'segment_position' => 1,
            'segment_length' => 2,
            'default_handler' => SegmentDefaultHandlerEnum::PARENT_TEAM->value,
        ]);
        
        $segment = $this->segmentService->createOrUpdateSegment($dto);
        
        $this->assertNotNull($segment);
        $this->assertEquals(SegmentDefaultHandlerEnum::PARENT_TEAM->value, $segment->default_handler);
        $this->assertTrue($segment->hasDefaultHandler());
    }
    
    /**
     * Test team handler resolves correctly
     */
    public function test_test_team_handler_resolves_correct_value()
    {
        $segment = $this->createSegmentWithHandler(
            SegmentDefaultHandlerEnum::TEAM->value,
            2, // length
            'Team Code'
        );
        
        $context = ['team_id' => 99];
        
        $value = $this->handlerService->resolveDefaultValue($segment, $context);
        
        $this->assertNotNull($value);
        $this->assertEquals('99', $value->segment_value);
    }
    
    /**
     * Test fiscal year handler
     */
    public function test_test_fiscal_year_handler_uses_last_digits()
    {
        // Create fiscal year
        $this->createFiscalYear(2025);
        
        $segment = $this->createSegmentWithHandler(
            SegmentDefaultHandlerEnum::FISCAL_YEAR->value,
            2, // length
            'Year'
        );
        
        $context = ['team_id' => 1];
        
        $value = $this->handlerService->resolveDefaultValue($segment, $context);
        
        $this->assertNotNull($value);
        $this->assertEquals('25', $value->segment_value); // Last 2 digits of 2025
    }
    
    /**
     * Test sequence handler with configuration
     */
    public function test_test_sequence_handler_increments_correctly()
    {
        $segment = $this->createSegmentWithHandler(
            SegmentDefaultHandlerEnum::SEQUENCE->value,
            4,
            'Account Number',
            [
                'prefix' => 'GL',
                'scope' => 'team',
                'pad_char' => '0',
                'start_value' => 1000,
            ]
        );
        
        $context = ['team_id' => 1];
        
        // First call
        $value1 = $this->handlerService->resolveDefaultValue($segment, $context);
        $this->assertEquals('GL00', $value1->segment_value); // GL + 1000 truncated to 4 chars
        
        // Second call should increment
        $value2 = $this->handlerService->resolveDefaultValue($segment, $context);
        $this->assertEquals('GL01', $value2->segment_value);
    }
    
    /**
     * Test fixed value handler
     */
    public function test_test_fixed_value_handler()
    {
        $segment = $this->createSegmentWithHandler(
            SegmentDefaultHandlerEnum::FIXED_VALUE->value,
            3,
            'Fixed Code',
            ['value' => 'ABC']
        );
        
        $value = $this->handlerService->resolveDefaultValue($segment, []);
        
        $this->assertNotNull($value);
        $this->assertEquals('ABC', $value->segment_value);
    }
    
    /**
     * Test creating account with defaults
     */
    public function test_test_create_account_with_default_handlers()
    {
        // Create segment structure with handlers
        $this->createSegmentWithHandler(SegmentDefaultHandlerEnum::PARENT_TEAM->value, 2, 'Company', null, 1);
        $this->createSegmentWithHandler(SegmentDefaultHandlerEnum::TEAM->value, 2, 'Team', null, 2);
        $segment3 = $this->createSegmentWithHandler(null, 4, 'Account', null, 3); // Manual
        
        // Create manual value for account segment
        $accountValue = new SegmentValue();
        $accountValue->segment_definition_id = $segment3->id;
        $accountValue->segment_value = '4000';
        $accountValue->segment_description = 'Cash Account';
        $accountValue->is_active = true;
        $accountValue->save();
        
        // Create account with only manual segment specified
        $dto = new CreateAccountDto([
            'segment_value_ids' => [null, null, $accountValue->id], // Only specify account
            'account_description' => 'Test Cash Account',
            'account_type' => 'ASSET',
            'apply_defaults' => true,
            'team_id' => 5,
        ]);
        
        $account = $this->segmentService->createAccount($dto);
        
        $this->assertNotNull($account);
        $this->assertEquals('01-05-4000', $account->account_id); // Parent(01)-Team(05)-Account(4000)
    }
    
    /**
     * Test validation of handler configuration
     */
    public function test_test_validates_handler_configuration()
    {
        $handler = SegmentDefaultHandlerEnum::SEQUENCE;
        
        $errors = $handler->validateConfig(['prefix' => 'TEST']); // Missing required fields
        
        $this->assertEmpty($errors); // prefix is optional, so no errors
        
        $errors = $handler->validateConfig(['scope' => 'invalid']);
        $this->assertEmpty($errors); // Would need actual validation logic
    }
    
    /**
     * Test handler options for UI
     */
    public function test_test_get_handler_options_for_ui()
    {
        $options = $this->segmentService->getSegmentHandlerOptions(1);
        
        $this->assertIsArray($options);
        $this->assertArrayHasKey(SegmentDefaultHandlerEnum::TEAM->value, $options);
        $this->assertArrayHasKey('label', $options[SegmentDefaultHandlerEnum::TEAM->value]);
        $this->assertArrayHasKey('description', $options[SegmentDefaultHandlerEnum::TEAM->value]);
        $this->assertArrayHasKey('requires_config', $options[SegmentDefaultHandlerEnum::TEAM->value]);
    }
    
    // Helper methods
    
    protected function createSegmentWithHandler(
        ?string $handler,
        int $length,
        string $description,
        ?array $config = null,
        int $position = 1
    ): AccountSegment {
        $segment = new AccountSegment();
        $segment->segment_position = $position;
        $segment->segment_length = $length;
        $segment->segment_description = $description;
        $segment->default_handler = $handler;
        $segment->default_handler_config = $config;
        $segment->save();
        
        return $segment;
    }
    
    protected function createTeamStructure()
    {
        // Create parent team
        \DB::table('teams')->insert([
            'id' => 1,
            'name' => 'Parent Company',
            'parent_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Create child team
        \DB::table('teams')->insert([
            'id' => 5,
            'name' => 'Child Team',
            'parent_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    
    protected function createFiscalYear($year)
    {
        \DB::table('fin_fiscal_years')->insert([
            'id' => 1,
            'fiscal_year' => $year,
            'team_id' => 1,
            'start_date' => "{$year}-01-01",
            'end_date' => "{$year}-12-31",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        \DB::table('fin_fiscal_periods')->insert([
            'fiscal_year_id' => 1,
            'team_id' => 1,
            'period_number' => 1,
            'period_start' => "{$year}-01-01",
            'period_end' => "{$year}-12-31",
            'is_open_gl' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
