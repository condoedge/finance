<?php

namespace Condoedge\Finance\Events;

use Condoedge\Communications\EventsHandling\Contracts\CommunicableEvent;
use Condoedge\Communications\EventsHandling\Contracts\DatabaseCommunicableEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Condoedge\Communications\Recipients\RecipientOverride;

class InvoiceSent implements CommunicableEvent, DatabaseCommunicableEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    protected $invoice;
    protected $customEmail;

    public function __construct($invoice, $customEmail = null)
    {
        $this->invoice = $invoice;
        $this->customEmail = $customEmail;
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
        $customer = $this->invoice->mainCustomer;

        if ($this->customEmail) {
            return collect([RecipientOverride::for($customer)->withEmail($this->customEmail)]);
        }

        return collect([]);
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
            $invoicePage => __('finance-invoice-page'),
        ];
    }
}
