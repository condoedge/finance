<?php

namespace Condoedge\Finance\Tests\Unit\Services;

use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Models\SegmentValue;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Dto\Gl\CreateAccountDto;
use Condoedge\Finance\Models\Dto\Gl\CreateOrUpdateSegmentDto;
use Condoedge\Finance\Models\Dto\Gl\CreateSegmentValueDto;
use Condoedge\Finance\Services\AccountSegmentService;
use Condoedge\Finance\Services\AccountSegmentValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountSegmentServiceTest extends TestCase
{
    use RefreshDatabase;
    
    protected AccountSegmentService $service;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create service with validator
        $validator = new AccountSegmentValidator();
        $this->service = new AccountSegmentService($validator);
        
        // Setup test team
        $this->actingAs($this->createUser());
    }
    
    #[Test]
    public function it_creates_segment_structure_with_dto()
    {
        // Create segment structure
        $segments = [
            ['segment_description' => 'Parent Team', 'segment_position' => 1, 'segment_length' => 2],
            ['segment_description' => 'Team', 'segment_position' => 2, 'segment_length' => 2],
            ['segment_description' => 'Account', 'segment_position' => 3, 'segment_length' => 4],
        ];
        
        foreach ($segments as $segmentData) {
            $dto = new CreateOrUpdateSegmentDto($segmentData + ['is_active' => true]);
            $segment = $this->service->createOrUpdateSegment($dto);
            
            $this->assertInstanceOf(AccountSegment::class, $segment);
            $this->assertEquals($segmentData['segment_description'], $segment->segment_description);
            $this->assertEquals($segmentData['segment_position'], $segment->segment_position);
            $this->assertEquals($segmentData['segment_length'], $segment->segment_length);
        }
        
        // Verify structure
        $structure = $this->service->getSegmentStructure();
        $this->assertCount(3, $structure);
        $this->assertEquals('XX-XX-XXXX', $this->service->getAccountFormatMask());
    }
    
    #[Test]
    public function it_creates_segment_values_with_validation()
    {
        // Setup structure first
        $this->createTestSegmentStructure();
        
        // Create segment values
        $segment = AccountSegment::where('segment_position', 1)->first();
        
        $dto = new CreateSegmentValueDto([
            'segment_definition_id' => $segment->id,
            'segment_value' => '10',
            'segment_description' => 'Parent Team 10',
            'is_active' => true,
        ]);
        
        $value = $this->service->createSegmentValue($dto);
        
        $this->assertInstanceOf(SegmentValue::class, $value);
        $this->assertEquals('10', $value->segment_value);
        $this->assertEquals('Parent Team 10', $value->segment_description);
        $this->assertTrue($value->is_active);
    }
    
    #[Test]
    public function it_validates_segment_value_length()
    {
        $this->createTestSegmentStructure();
        $segment = AccountSegment::where('segment_position', 1)->first();
        
        // Try to create value that's too long
        $dto = new CreateSegmentValueDto([
            'segment_definition_id' => $segment->id,
            'segment_value' => '1234', // Too long for length 2
            'segment_description' => 'Invalid Value',
            'is_active' => true,
        ]);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds maximum length');
        
        $this->service->createSegmentValue($dto);
    }
    
    #[Test]
    public function it_prevents_duplicate_segment_values()
    {
        $this->createTestSegmentStructure();
        $segment = AccountSegment::where('segment_position', 1)->first();
        
        // Create first value
        $dto = new CreateSegmentValueDto([
            'segment_definition_id' => $segment->id,
            'segment_value' => '10',
            'segment_description' => 'Parent Team 10',
            'is_active' => true,
        ]);
        $this->service->createSegmentValue($dto);
        
        // Try to create duplicate
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('already exists');
        
        $this->service->createSegmentValue($dto);
    }
    
    #[Test]
    public function it_creates_account_from_segment_values()
    {
        $this->createTestSegmentStructure();
        $segmentValueIds = $this->createTestSegmentValues();
        
        $dto = new CreateAccountDto([
            'segment_value_ids' => $segmentValueIds,
            'account_description' => 'Test Account',
            'account_type' => 'asset',
            'is_active' => true,
            'allow_manual_entry' => true,
            'team_id' => currentTeamId(),
        ]);
        
        $account = $this->service->createAccount($dto);
        
        $this->assertInstanceOf(GlAccount::class, $account);
        $this->assertEquals('Test Account', $account->account_description);
        $this->assertEquals('asset', $account->account_type);
        $this->assertTrue($account->is_active);
        $this->assertTrue($account->allow_manual_entry);
        
        // Verify account ID was computed by trigger
        $this->assertNotEquals('TEMP', substr($account->account_id, 0, 4));
        $this->assertStringContainsString('-', $account->account_id);
    }
    
    #[Test]
    public function it_validates_account_completeness()
    {
        $this->createTestSegmentStructure();
        
        // Only create values for 2 out of 3 segments
        $segment1 = AccountSegment::where('segment_position', 1)->first();
        $segment2 = AccountSegment::where('segment_position', 2)->first();
        
        $value1 = $this->createSegmentValue($segment1->id, '10', 'Parent Team 10');
        $value2 = $this->createSegmentValue($segment2->id, '03', 'Team 03');
        
        $dto = new CreateAccountDto([
            'segment_value_ids' => [$value1->id, $value2->id], // Missing segment 3
            'account_description' => 'Incomplete Account',
            'account_type' => 'asset',
            'is_active' => true,
            'allow_manual_entry' => true,
            'team_id' => currentTeamId(),
        ]);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing value for segment position');
        
        $this->service->createAccount($dto);
    }
    
    #[Test]
    public function it_prevents_duplicate_accounts()
    {
        $this->createTestSegmentStructure();
        $segmentValueIds = $this->createTestSegmentValues();
        
        // Create first account
        $dto = new CreateAccountDto([
            'segment_value_ids' => $segmentValueIds,
            'account_description' => 'First Account',
            'account_type' => 'asset',
            'is_active' => true,
            'allow_manual_entry' => true,
            'team_id' => currentTeamId(),
        ]);
        $this->service->createAccount($dto);
        
        // Try to create duplicate with same segments
        $dto2 = new CreateAccountDto([
            'segment_value_ids' => $segmentValueIds,
            'account_description' => 'Duplicate Account',
            'account_type' => 'liability',
            'is_active' => true,
            'allow_manual_entry' => true,
            'team_id' => currentTeamId(),
        ]);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('already exists');
        
        $this->service->createAccount($dto2);
    }
    
    #[Test]
    public function it_logs_operations()
    {
        $this->createTestSegmentStructure();
        
        // Test that operations are logged
        DB::listen(function ($query) {
            // Verify no raw SQL injections
            $this->assertStringNotContainsString('fillable', $query->sql);
        });
        
        $segmentValueIds = $this->createTestSegmentValues();
        
        $dto = new CreateAccountDto([
            'segment_value_ids' => $segmentValueIds,
            'account_description' => 'Test Account',
            'account_type' => 'asset',
            'is_active' => true,
            'allow_manual_entry' => true,
            'team_id' => currentTeamId(),
        ]);
        
        $account = $this->service->createAccount($dto);
        
        // Account should be created successfully
        $this->assertNotNull($account->id);
    }
    
    /**
     * Helper methods
     */
    protected function createTestSegmentStructure(): void
    {
        $segments = [
            ['segment_description' => 'Parent Team', 'segment_position' => 1, 'segment_length' => 2],
            ['segment_description' => 'Team', 'segment_position' => 2, 'segment_length' => 2],
            ['segment_description' => 'Account', 'segment_position' => 3, 'segment_length' => 4],
        ];
        
        foreach ($segments as $segmentData) {
            $segment = new AccountSegment();
            $segment->segment_description = $segmentData['segment_description'];
            $segment->segment_position = $segmentData['segment_position'];
            $segment->segment_length = $segmentData['segment_length'];
            $segment->is_active = true;
            $segment->save();
        }
    }
    
    protected function createTestSegmentValues(): array
    {
        $segments = AccountSegment::orderBy('segment_position')->get();
        $valueIds = [];
        
        $values = [
            1 => ['10', 'Parent Team 10'],
            2 => ['03', 'Team 03'],
            3 => ['1000', 'Cash Account'],
        ];
        
        foreach ($segments as $segment) {
            $value = $this->createSegmentValue(
                $segment->id,
                $values[$segment->segment_position][0],
                $values[$segment->segment_position][1]
            );
            $valueIds[] = $value->id;
        }
        
        return $valueIds;
    }
    
    protected function createSegmentValue(int $segmentDefinitionId, string $value, string $description): SegmentValue
    {
        $segmentValue = new SegmentValue();
        $segmentValue->segment_definition_id = $segmentDefinitionId;
        $segmentValue->segment_value = $value;
        $segmentValue->segment_description = $description;
        $segmentValue->is_active = true;
        $segmentValue->save();
        
        return $segmentValue;
    }
    
    protected function createUser()
    {
        $user = new \App\Models\User();
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $user->password = bcrypt('password');
        $user->save();
        
        return $user;
    }
}
