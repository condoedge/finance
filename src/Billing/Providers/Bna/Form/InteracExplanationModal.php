<?php

namespace Condoedge\Finance\Billing\Providers\Bna\Form;

use Condoedge\Finance\Kompo\Common\Modal;

class InteracExplanationModal extends Modal
{
    protected $_Title = 'translate.finance-interac-explanation-modal';

    protected $redirectUrl;

    public function created()
    {
        $this->redirectUrl = $this->prop('redirect_url');
    }

    public function body()
    {
        return _Rows(
            _Html('translate.interac-explanation-modal-body')->class('mb-4'),
            _Img('images/vendor/kompo-finance/interac-explanation.png')
                ->class('w-full h-24 mb-12')->bgCover(),
            _Link('translate.go-to-interac-page')->button()->href($this->redirectUrl)
                ->inNewTab(),
        );
    }
}
