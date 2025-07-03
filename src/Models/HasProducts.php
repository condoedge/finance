<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Models\Product;

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

    public function productsCountInTotal()
    {
        return $this->products()->countInTotal()->get();
    }

    public function createOrUpdateCost($type, $amount, $name = '')
    {
        return Product::createOrUpdateCost($this, $type, $amount, $name);
    }

    public function getAmount()
    {
        return $this->getAllCostsCountInTotal()->sum(fn ($cost) => $cost->getAmount());
    }

    public function getAllCostsCountInTotal()
    {
        return $this->productsCountInTotal()->merge($this->parentCostsCountInTotal());
    }

    public function getCommisions()
    {
        return $this->getParentCommisionCosts()->sum(fn ($cost) => $cost->getCommissionAmount());
    }

    public function getParentCommisionCosts()
    {
        return $this->getParentCosts()->filter(fn ($cost) => $cost->getCommissionAmount());
    }

    public function getProfit()
    {
        return $this->getAmount() - $this->getCommisions();
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
