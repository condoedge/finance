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
        return [
            'current_user' => auth()->user(),
            'date' => now(),
            ...$this->context,
        ];
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

    public function handleProductRebates($product)
    {
        $rebates = $product->rebates()->orderBy('order')->get();
        $rebatesDiscount = 0;
        $accumulatedDiscount = 0; // Just when the rebate has is_accumulable = true

        foreach ($rebates as $rebate) {
            $handlerKey = $rebate->rebate_logic_type;

            $handler = $this->getRebateHandler($handlerKey);

            if ($handler->shouldApplyRebate($product, $rebate)) {
                // They are accumulable so we must send the current discount to the handler so it can calculate the next rebate based on the already applied discounts
                $discountAmount = $handler->calculateRebate($product, $rebate, $rebatesDiscount);
                $rebatesDiscount += $discountAmount;
                
                if ($rebate->is_accumulable) {
                    $accumulatedDiscount += $discountAmount;
                }
            }
        }

        return $rebatesDiscount;
    }
}