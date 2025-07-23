<?php

namespace Condoedge\Finance\Events;

use Condoedge\Communications\EventsHandling\Contracts\CommunicableEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class InvoiceSent implements CommunicableEvent
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
        ];
    }

    public static function getName(): string
    {
        return __('translate.invoice-sent');
    }

    public function getCommunicables(): Collection|array
    {
        return collect([$this->invoice->mainCustomer]);
    }

    public static function validVariablesIds($specificField = null): ?array
    {
        return ['invoices.*'];
    }
}
