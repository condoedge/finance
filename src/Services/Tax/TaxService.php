<?php

namespace Condoedge\Finance\Services\Tax;

use Closure;
use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\Tax;
use Condoedge\Finance\Models\TaxGroup;
use Condoedge\Utils\Facades\GlobalConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Tax Service Implementation
 *
 * Handles all tax business logic including tax calculations, tax group management,
 * validation, and invoice tax application.
 *
 * This implementation can be easily overridden by binding a custom
 * implementation to the TaxServiceInterface in your service provider.
 */
class TaxService implements TaxServiceInterface
{
    protected $resolverTaxGroupId;

    /**
     * Get active taxes for team
     */
    public function getActiveTaxes(): Collection
    {
        return Tax::active()
            ->orderBy('name')
            ->get();
    }

    /**
     * Calculate tax amount for base amount
     */
    public function calculateTaxAmount(SafeDecimal $baseAmount, Tax $tax): SafeDecimal
    {
        // Calculate: baseAmount * (tax_rate / 100)
        return $baseAmount->multiply($tax->rate);
    }

    /**
     * Get default tax IDs for a given context
     */
    public function getDefaultTaxesIds(array $context = []): Collection
    {
        $taxGroupId = $this->resolverTaxGroupId
            ? call_user_func($this->resolverTaxGroupId, $context)
            : $this->defaultResolveTaxGroupId($context);

        if (!$taxGroupId) {
            return collect(); // Return empty collection if no tax group found
        }

        $taxGroup = TaxGroup::findOrFail($taxGroupId);

        return $taxGroup->taxes()->active()->pluck('fin_taxes.id');
    }

    public function setTaxGroupIdResolver(callable|Closure $resolver): void
    {
        if (!is_callable($resolver)) {
            throw new InvalidArgumentException('Tax group ID resolver must be a callable');
        }

        $this->resolverTaxGroupId = $resolver;
    }

    /**
     * Resolve tax group ID for invoice
     */
    protected function defaultResolveTaxGroupId(array $context): int
    {
        $invoice = $context['invoice'] ?? null;

        return $invoice?->customer?->defaultAddress->tax_group_id
            ?? GlobalConfig::getOrFail('default_tax_group_id');
    }

    /**
     * Create tax group with taxes
     */
    public function createTaxGroup(string $name, Collection $taxesIds): TaxGroup
    {
        return DB::transaction(function () use ($name, $taxesIds) {
            // Create tax group
            $taxGroup = new TaxGroup();
            $taxGroup->name = $name;
            $taxGroup->save();

            // Attach taxes
            $taxGroup->taxes()->attach($taxesIds->toArray());

            return $taxGroup->refresh();
        });
    }

    public function upsertTaxGroup(array|Collection $taxesIds): TaxGroup
    {
        return DB::transaction(function () use ($taxesIds) {
            $taxesIds = collect($taxesIds)->filter()->values();

            $defaultName = $this->getDefaultTaxGroupName($taxesIds);
            $taxesCount = $taxesIds->count();

            // Check if tax group already exists based on taxes ids
            $taxGroup = TaxGroup::whereHas('taxes', function ($query) use ($taxesIds) {
                $query->whereIn('fin_taxes.id', $taxesIds->toArray());
            })
                ->withCount('taxes')
                ->having('taxes_count', '=', $taxesCount)
                ->whereDoesntHave('taxes', function ($query) use ($taxesIds) {
                    $query->whereNotIn('fin_taxes.id', $taxesIds->toArray());
                })
                ->first();

            if (!$taxGroup) {
                $taxGroup = $this->createTaxGroup($defaultName, $taxesIds);
            }

            return $taxGroup;
        });
    }

    protected function getDefaultTaxGroupName(Collection $taxesIds): string
    {
        return collect($taxesIds)
            ->map(function ($id) {
                $tax = Tax::find($id);

                return $tax ? $tax->name : null;
            })
            ->filter()
            ->implode(' + ') ?: __('translate.no-taxes');
    }

    /**
     * Update tax group taxes
     */
    public function updateTaxGroupTaxes(TaxGroup $taxGroup, Collection $taxesIds): TaxGroup
    {
        return DB::transaction(function () use ($taxGroup, $taxesIds) {
            // Sync taxes (this will remove old and add new)
            $taxGroup->taxes()->sync($taxesIds->toArray());

            return $taxGroup->refresh();
        });
    }

    /**
     * Calculate compound tax amount
     */
    public function calculateCompoundTaxAmount(SafeDecimal $baseAmount, Collection $taxes, bool $compoundMode = false): SafeDecimal
    {
        if (!$compoundMode) {
            return $this->calculateSimpleTaxSum($baseAmount, $taxes);
        }

        // Compound mode: each tax applies to amount + previous taxes
        $currentAmount = $baseAmount;
        $totalTax = new SafeDecimal('0.00');

        foreach ($taxes as $tax) {
            $taxAmount = $currentAmount->multiply($tax->rate);
            $totalTax = $totalTax->add($taxAmount);
            $currentAmount = $currentAmount->add($taxAmount);
        }

        return $totalTax;
    }

    /* PROTECTED METHODS - Can be overridden for customization */

    /**
     * Calculate simple tax sum (non-compound)
     */
    protected function calculateSimpleTaxSum(SafeDecimal $baseAmount, Collection $taxes): SafeDecimal
    {
        $totalTax = new SafeDecimal('0.00');

        foreach ($taxes as $tax) {
            $taxAmount = $this->calculateTaxAmount($baseAmount, $tax);
            $totalTax = $totalTax->add($taxAmount);
        }

        return $totalTax;
    }

    /**
     * Get tax group by ID with validation
     */
    protected function getTaxGroupById(int $taxGroupId, ?int $teamId = null): TaxGroup
    {
        return TaxGroup::forTeam($teamId)->findOrFail($taxGroupId);
    }
}
