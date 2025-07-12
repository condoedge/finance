<?php

interface PaymentProcessorInterface
{
    // public function initializePayment(PaymentContext $context): PaymentInitResponse;
    public function processPayment(PaymentContext $context);
}