<?php

namespace Condoedge\Finance\Models;

interface CustomableContract
{
    public function upsertCustomerFromThisModel();
    public function fillCustomerFromThisModel($customer);

    public function updateFromCustomer($customer);

    public static function getVisualName();
    public static function getOptionsForCustomerForm($search = null);

    // Addresses
    public function getFirstValidAddress();
}
