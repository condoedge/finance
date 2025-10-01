<?php

namespace Condoedge\Finance\Events;

use Condoedge\Communications\EventsHandling\Contracts\CommunicableEvent;
use Condoedge\Communications\EventsHandling\Contracts\DatabaseCommunicableEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class InvoiceSent implements CommunicableEvent, DatabaseCommunicableEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    protected $invoice;

    public function __construct($invoice)
    {
        $this->invoice = $invoice;
    }

    public function getParams(): array
    {
        return [
            'invoice' => $this->invoice,

            'about_type' => 'invoice',
            'about_id' => $this->invoice->id,
        ];
    }

    public static function getName(): string
    {
        return __('finance-invoice-sent');
    }

    public function getCommunicables(): Collection|array
    {
        return collect([$this->invoice->mainCustomer]);
    }

    public static function validVariablesIds($specificField = null, $context = []): ?array
    {
        return ['invoices.*'];
    }

    public static function getValidRoutes(): array
    {
        $invoicePage = route('invoices.show', ['id' => 'to_be_replaced']);
        $invoicePage = str_replace('to_be_replaced', getVarBuilt('invoice.id', 'brace'), $invoicePage);

        return [
            $invoicePage => __('translate.invoice-page'),
        ];
    }
}
