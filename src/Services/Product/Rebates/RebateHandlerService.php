<?php

namespace Condoedge\Finance\Services\Product\Rebates;

class RebateHandlerService
{
    protected $context;

    public function __construct($context = [])
    {
        $this->context = $context;
    }

    public function getContext()
    {
        return $this->context;
    }

    public function getRebateHandlers()
    {
        return config('kompo-finance.rebate_handlers');
    }

    public function getRebateHandlersWithLabels()
    {
        $handlers = $this->getRebateHandlers();
        $handlersWithLabels = [];

        foreach ($handlers as $key => $handlerClass) {
            $handlerInstance = new $handlerClass($this->getContext());
            $handlersWithLabels[$key] = $handlerInstance->getHandlerLabel();
        }

        return $handlersWithLabels;
    }

    /**
     * @return \Condoedge\Finance\Services\Product\Rebates\AbstractRebateHandler 
     **/
    public function getRebateHandler(string $handlerKey)
    {
        $handlers = $this->getRebateHandlers();

        if (!isset($handlers[$handlerKey])) {
            throw new \InvalidArgumentException("Rebate handler with key '{$handlerKey}' not found.");
        }

        $handlerClass = $handlers[$handlerKey];

        return new $handlerClass($this->getContext());
    }

    public function getDiscountAmount($product)
    {
        return collect($this->normalizeRebatesToInvoiceDetails($product))->sum('unit_price');
    }

    public function normalizeRebatesToInvoiceDetails($product, $invoiceId = null)
    {
        $rebates = $product->rebates()->orderBy('order')->get();
        $invoiceDetails = [];
        $accumulatedDiscount = 0;

        foreach ($rebates as $rebate) {
            $handlerKey = $rebate->rebate_logic_type;
            $handler = $this->getRebateHandler($handlerKey);

            if ($handler->shouldApplyRebate($product, $rebate)) {
                $discountAmount = $handler->calculateRebate($product, $rebate, $accumulatedDiscount);
                
                if ($rebate->is_accumulable) {
                    $accumulatedDiscount += $discountAmount;
                }

                $invoiceDetails[] = [
                    'name' => "Rebate: {$rebate->handler_label}",
                    'description' => "Applied rebate logic: {$rebate->handler_params_label}",
                    'unit_price' => -$discountAmount,
                    'quantity' => 1,
                    'taxesIds' => $rebate->product->taxes_ids ?: [],
                    'invoiceable_type' => 'rebate',
                    'invoiceable_id' => $rebate->id,
                    'invoice_id' => $invoiceId,
                ];
            }
        }

        return $invoiceDetails;
    }
}