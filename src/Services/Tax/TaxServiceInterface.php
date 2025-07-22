<?php

namespace Condoedge\Finance\Services\Tax;

use Closure;
use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Models\InvoiceDetail;
use Condoedge\Finance\Models\Tax;
use Condoedge\Finance\Models\TaxGroup;
use Illuminate\Support\Collection;

/**
 * Interface for Tax Service
 *
 * This interface allows easy override of tax business logic
 * by implementing this interface in external packages or custom services.
 */
interface TaxServiceInterface
{
    /**
     * Get active taxes for a specific team
     *
     *
     * @return Collection<Tax>
     */
    public function getActiveTaxes(): Collection;

    /**
     * This is reduntant, we're doing it into the database.
     * Calculate total tax amount based on base amount and tax
     *
     * @param InvoiceDetail $invoiceDetail
     *
     * @return SafeDecimal
     */
    public function calculateTaxAmount(SafeDecimal $baseAmount, Tax $tax): SafeDecimal;

    /**
     * Create tax group with taxes
     *
     * @param string $name
     * @param Collection<int> $taxIds
     *
     * @return TaxGroup
     *
     * @throws \Exception When tax IDs are invalid
     */
    public function createTaxGroup(string $name, Collection $taxIds): TaxGroup;

    public function upsertTaxGroup(array|Collection $taxIds): TaxGroup;

    /**
     * Update tax group taxes
     *
     * @param TaxGroup $taxGroup
     * @param Collection<int> $taxIds
     *
     * @return TaxGroup
     *
     * @throws \Exception When tax IDs are invalid
     */
    public function updateTaxGroupTaxes(TaxGroup $taxGroup, Collection $taxIds): TaxGroup;

    /**
     * Calculate compound tax amount (tax on tax)
     *
     * @param SafeDecimal $baseAmount
     * @param Collection<Tax> $taxes
     * @param bool $compoundMode
     *
     * @return SafeDecimal
     */
    public function calculateCompoundTaxAmount(SafeDecimal $baseAmount, Collection $taxes, bool $compoundMode = false): SafeDecimal;

    public function getDefaultTaxesIds(array $context = []): Collection;

    public function setTaxGroupIdResolver(callable|Closure $resolver): void;
}
