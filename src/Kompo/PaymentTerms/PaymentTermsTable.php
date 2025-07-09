<?php

namespace Condoedge\Finance\Kompo\PaymentTerms;

use Condoedge\Finance\Models\PaymentTerm;
use Condoedge\Utils\Kompo\Common\WhiteTable;

class PaymentTermsTable extends WhiteTable
{
    public $id = 'payment-terms-table';

    public function query()
    {
        return PaymentTerm::query();
    }

    public function top()
    {
        return _Rows(
            _Button('translate.add-payment-term')
                ->selfGet('getPaymentTermForm')->inModal()
        )->class('mb-4');
    }

    public function headers()
    {
        return [
            _Th('translate.term-name'),
            _Th('translate.details'),
            _Th()->class('w-8')
        ];
    }

    public function render($term)
    {
        return _TableRow(
            _Html($term->term_name),
            _Rows(
                _Html($term->term_type->label()),
                !$term->settings ? null : _Rows(
                    collect($term->settings)->map(function ($value, $key) {
                        return _Html($key . ': ' . $value)->class('text-sm text-gray-600');
                    }),
                )
            ),
            _TripleDotsDropdown(
                _Link('translate.edit')->selfGet('getPaymentTermForm', ['id' => $term->id])->inModal(),
            )
        );
    }

    public function getPaymentTermForm($id = null)
    {
        return new PaymentTermForm($id);
    }
}