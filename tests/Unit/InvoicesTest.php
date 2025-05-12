<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InvoicesTest extends TestCase
{
    public function testOfTests()
    {
        $invoices = DB::table('fin_invoices')->get();
        $this->assertEmpty($invoices);
    }

    public function testOfTests2()
    {
        $this->assertTrue(true);
    }
}