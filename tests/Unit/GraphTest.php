<?php

namespace Tests\Unit;

use Condoedge\Finance\Services\Graph;
use Tests\TestCase;

class GraphTest extends TestCase
{
    public function test_graph_calculates_descendants_correctly()
    {
        $graph = new Graph([
            'Invoice' => ['InvoiceDetail'],
            'InvoiceDetail' => ['InvoiceDetailTax'],
            'Customer' => ['Invoice'],
        ]);

        $descendants = $graph->getDescendants('Invoice');

        $this->assertContains('InvoiceDetail', $descendants);
        $this->assertContains('InvoiceDetailTax', $descendants);
    }


    public function test_graph_calculates_ancestors_correctly()
    {
        $graph = new Graph([
            'Customer' => ['Invoice'],
            'Invoice' => ['InvoiceDetail'],
            'InvoiceDetail' => ['InvoiceDetailTax'],
        ]);

        $ancestors = $graph->getAncestors('InvoiceDetailTax');

        $this->assertContains('InvoiceDetail', $ancestors);
        $this->assertContains('Invoice', $ancestors);
        $this->assertContains('Customer', $ancestors);
    }


    public function test_graph_finds_roots_correctly()
    {
        $graph = new Graph([
            'Customer' => ['Invoice'],
            'Invoice' => ['InvoiceDetail'],
            'Account' => ['Transaction'],
        ]);

        $roots = $graph->getGraphRoots();

        $this->assertContains('Customer', $roots);
        $this->assertContains('Account', $roots);
        $this->assertNotContains('Invoice', $roots);
        $this->assertNotContains('InvoiceDetail', $roots);
    }
}
