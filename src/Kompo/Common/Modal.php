<?php

namespace Condoedge\Finance\Kompo\Common;

use Condoedge\Utils\Kompo\Common\Modal as BaseModal;

class Modal extends BaseModal
{
    use PreventsDuplicateSubmit;

    protected $noHeaderButtons = true;
}
