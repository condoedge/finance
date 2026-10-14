<?php

namespace Tests\Unit\Billing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SettlementFilesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('fin_settlement_files'));

        foreach ([
            'provider_code', 'remote_filename', 'local_path',
            'remote_size', 'sha256', 'fetched_at', 'imported_at',
            'import_result_json', 'last_error',
        ] as $col) {
            $this->assertTrue(
                Schema::hasColumn('fin_settlement_files', $col),
                "Expected column '{$col}' on fin_settlement_files"
            );
        }
    }

    public function test_unique_index_on_provider_filename_rejects_duplicates(): void
    {
        DB::table('fin_settlement_files')->insert([
            'provider_code' => 'moneris',
            'remote_filename' => 'SP1_20260525.csv',
            'local_path' => 'moneris/SP1_20260525.csv',
            'remote_size' => 1024,
            'sha256' => str_repeat('a', 64),
            'fetched_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('fin_settlement_files')->insert([
            'provider_code' => 'moneris',
            'remote_filename' => 'SP1_20260525.csv',
            'local_path' => 'moneris/SP1_20260525-dup.csv',
            'remote_size' => 1024,
            'sha256' => str_repeat('b', 64),
            'fetched_at' => now(),
        ]);
    }

    public function test_unique_index_on_provider_sha256_rejects_duplicate_content(): void
    {
        $hash = str_repeat('c', 64);

        DB::table('fin_settlement_files')->insert([
            'provider_code' => 'moneris',
            'remote_filename' => 'original.csv',
            'local_path' => 'moneris/original.csv',
            'remote_size' => 2048,
            'sha256' => $hash,
            'fetched_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('fin_settlement_files')->insert([
            'provider_code' => 'moneris',
            'remote_filename' => 'renamed.csv',
            'local_path' => 'moneris/renamed.csv',
            'remote_size' => 2048,
            'sha256' => $hash,
            'fetched_at' => now(),
        ]);
    }
}
