<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * get_detail_unit_price_with_sign() writes into a NOT NULL column. v0002 dropped the null
 * guard v0001 got from get_amount_using_sign_multiplier(), so a detail row the calling
 * transaction cannot read surfaced as "Column 'unit_price' cannot be null".
 *
 * It reports the real cause instead, and both functions now take the bigint the id column is.
 */
class HardenInvoiceDetailPriceFunctions extends Migration
{
    public function up()
    {
        $this->load('get_detail_unit_price_with_sign', 'v0003');
        $this->load('get_updated_tax_amount_for_taxes', 'v0002');
    }

    public function down()
    {
        $this->load('get_detail_unit_price_with_sign', 'v0002');
        $this->load('get_updated_tax_amount_for_taxes', 'v0001');
    }

    protected function load(string $function, string $version): void
    {
        $sql = file_get_contents(__DIR__ . "/../sql/functions/{$function}/{$function}_{$version}.sql");

        DB::unprepared(processDelimiters($sql));
    }
}
