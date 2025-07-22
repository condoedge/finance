<?php

namespace Condoedge\Finance\Kompo\Taxes;

use Condoedge\Finance\Facades\AccountSegmentService;
use Condoedge\Finance\Facades\TaxModel;
use Condoedge\Finance\Kompo\Common\Modal;

class TaxForm extends Modal
{
    public $model = TaxModel::class;

    protected $_Title = 'finance-add-new-tax';

    public function beforeSave()
    {
        $this->model->rate = request('pct_rate') / 100;
        $this->model->account_id = AccountSegmentService::createAccountFromLastValue(request('account_id'))->id;
    }

    public function body()
    {
        return _Rows(
            _Input('finance-name')
                ->name('name')
                ->required(),
            _InputNumber('finance-rate')
                ->name('pct_rate', false)
                ->required()
                ->default($this->model->rate?->multiply(100)->toFloat()),
            _AccountsSelect('finance-account', $this->model->account)
                ->name('account_id', false)
                ->required(),
            _Date('finance-valid-from')->name('valide_from')
                ->default(now()->format('Y-m-d')),
            _FlexEnd(
                _SubmitButton('generic.save')->closeModal()
                    ->alert('finance-tax-saved-successfully')
                    ->refresh('taxes-table'),
            )->class('mt-3'),
        );
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'pct_rate' => 'required|numeric|min:0.01|max:100',
            'account_id' => 'required|exists:fin_segment_values,id',
        ];
    }
}
