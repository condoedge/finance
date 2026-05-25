<?php

namespace Tests\Unit\Billing;

use Condoedge\Finance\Billing\Settlement\SettlementImportResult;
use Condoedge\Finance\Models\SettlementFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettlementFileModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_for_returns_null_when_no_row(): void
    {
        $this->assertNull(
            SettlementFile::findFor(teamId: 1, providerCode: 'moneris', filename: 'missing.csv')
        );
    }

    public function test_mark_fetched_creates_a_row_with_metadata(): void
    {
        $row = SettlementFile::markFetched(
            teamId: 7,
            providerCode: 'moneris',
            filename: 'SP1_20260525.csv',
            localPath: 'moneris/7/SP1_20260525.csv',
            size: 4321,
            sha256: str_repeat('f', 64),
        );

        $this->assertEquals(7, $row->team_id);
        $this->assertEquals('moneris', $row->provider_code);
        $this->assertEquals('SP1_20260525.csv', $row->remote_filename);
        $this->assertNotNull($row->fetched_at);
        $this->assertNull($row->imported_at);

        $found = SettlementFile::findFor(7, 'moneris', 'SP1_20260525.csv');
        $this->assertNotNull($found);
        $this->assertEquals($row->id, $found->id);
    }

    public function test_mark_imported_persists_result_json_and_timestamp(): void
    {
        $row = SettlementFile::markFetched(
            teamId: 7,
            providerCode: 'moneris',
            filename: 'SP1_20260525.csv',
            localPath: 'moneris/7/SP1_20260525.csv',
            size: 4321,
            sha256: str_repeat('e', 64),
        );

        $result = new SettlementImportResult(
            providerCode: 'moneris',
            rowsParsed: 10,
            matched: 9,
            unmatched: 1,
            unmatchedRefs: ['UNKNOWN_REF_1'],
        );

        $row->markImported($result);

        $reloaded = $row->fresh();
        $this->assertNotNull($reloaded->imported_at);
        $this->assertEquals(10, $reloaded->import_result_json['rowsParsed']);
        $this->assertEquals(9, $reloaded->import_result_json['matched']);
        $this->assertEquals(1, $reloaded->import_result_json['unmatched']);
    }

    public function test_mark_failed_records_error_without_setting_imported_at(): void
    {
        $row = SettlementFile::markFetched(
            teamId: 7,
            providerCode: 'moneris',
            filename: 'SP1_20260525.csv',
            localPath: 'moneris/7/SP1_20260525.csv',
            size: 4321,
            sha256: str_repeat('d', 64),
        );

        $row->markFailed('Parse error: invalid CSV');

        $reloaded = $row->fresh();
        $this->assertNull($reloaded->imported_at);
        $this->assertEquals('Parse error: invalid CSV', $reloaded->last_error);
    }
}
