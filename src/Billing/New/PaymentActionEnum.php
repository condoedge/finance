<?php

enum PaymentActionEnum: string
{
    use \Kompo\Models\Traits\EnumKompo;

    case REDIRECT = 'redirect';
}