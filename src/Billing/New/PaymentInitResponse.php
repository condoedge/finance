<?php


class PaymentInitResponse
{
    public function __construct(
        public readonly PaymentActionEnum $action,
        public readonly PaymentContext $context,
        public readonly array $data = [], // Additional data for the action
    ) {}

    public static function redirect(PaymentContext $context, array $data = []): self
    {
        return new self(PaymentActionEnum::REDIRECT, $context, $data);
    }

    public static function form(PaymentContext $context, array $data = []): self
    {
        return new self(PaymentActionEnum::FORM, $context, $data);
    }

    public static function directResponse(PaymentContext $context, array $data = []): self
    {
        return new self(PaymentActionEnum::DIRECT_RESPONSE, $context, $data);
    }
}