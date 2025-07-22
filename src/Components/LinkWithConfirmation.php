<?php

namespace Condoedge\Finance\Components;

use Kompo\DeleteLink;

class LinkWithConfirmation extends DeleteLink
{
    protected function initialize($label)
    {
        parent::initialize($label);

        $this->deleteTitle(__('translate.are-you-sure'));
    }

    public function confirmationTitle($title)
    {
        $this->config([
            'deleteTitle' => __($title),
        ]);

        return $this;
    }
}
