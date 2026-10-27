<?php

namespace Condoedge\Finance\Models;

use Illuminate\Support\Collection;

interface CustomableContract
{
    public function upsertCustomerFromThisModel();
    public function fillCustomerFromThisModel($customer);

    public function updateFromCustomer($customer);

    public static function getVisualName();
    public static function getOptionsForCustomerForm($search = null);

    // Addresses
    public function getFirstValidAddress();

    // Invoice recipient search
    public function scopeInvoiceRecipientsWithin($query, int $teamId);
    public static function searchForInvoiceRecipient(string $search, int $teamId): Collection;
    /**
     * Who is actually billed when this model is picked; empty = nobody can be.
     * Candidates are always of this model's own class: the option key carries only their id.
     */
    public function invoiceRecipientsFor(int $teamId): Collection;
    public function invoiceRecipientName(): string;
    /** @param CustomableContract|null $billed null = nobody to bill */
    public function invoiceRecipientLabel(?CustomableContract $billed);
}
