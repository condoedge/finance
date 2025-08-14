<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Casts\SafeDecimal;

trait HasProducts
{
    public function products()
    {
        return $this->morphMany(Product::class, 'productable');
    }

    public function getTeamCommission()
    {
        return $this->products()
            ->teamCommission()->first();
    }

    public function getProductByKey($key)
    {
        return $this->products()
            ->where('key', $key)
            ->first();
    }

    public function productsCountInTotal()
    {
        return $this->products()->countInTotal()->get();
    }

    public function createOrUpdateCost(ProductTypeEnum $type, mixed $amount, string $name = '', string $key = null)
    {
        return Product::createOrUpdateCost($this, $type, new SafeDecimal($amount), $name, key: $key);
    }

    public function getAmount()
    {
        return $this->getAllCostsCountInTotal()->sumDecimals(fn ($cost) => $cost->getAmount());
    }

    public function getAllCostsCountInTotal()
    {
        return $this->productsCountInTotal()->merge($this->parentCostsCountInTotal());
    }

    public function getCommisions()
    {
        return $this->getParentCommisionCosts()->sumDecimals(fn ($cost) => $cost->getCommissionAmount());
    }

    public function getParentCommisionCosts()
    {
        return $this->getParentCosts()->filter(fn ($cost) => $cost->getCommissionAmount());
    }

    public function getProfit()
    {
        return $this->getAmount()?->subtract($this->getCommisions()) ?? safeDecimal(0);
    }

    public function getParentCosts($withActual = false)
    {
        if (!$this->parentTemplate) {
            return $this->products;
        }

        return $this->parentTemplate->getParentCosts(true)->merge($withActual ? $this->products : []);
    }

    public function parentCostsCountInTotal()
    {
        return $this->getParentCosts()->filter(fn ($cost) => $cost->countInTotal());
    }
}
