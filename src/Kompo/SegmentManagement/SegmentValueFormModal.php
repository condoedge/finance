<?php

namespace Condoedge\Finance\Kompo\SegmentManagement;

use Condoedge\Finance\Facades\AccountSegmentService;
use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Models\AccountTypeEnum;
use Condoedge\Finance\Models\Dto\Gl\CreateSegmentValueDto;
use Condoedge\Finance\Models\Dto\Gl\UpdateSegmentValueDto;
use Condoedge\Finance\Models\SegmentValue;
use Condoedge\Utils\Kompo\Common\Modal;

class SegmentValueFormModal extends Modal
{
    public $_Title = 'finance-create-account-segment-value';
    public $model = SegmentValue::class;

    protected $position;
    protected $isRealAccount;

    public function created()
    {
        $this->position = $this->model->segmentDefinition?->segment_position ?: $this->prop('segment_position') ?: AccountSegmentService::getLastSegmentPosition();

        $this->isRealAccount = AccountSegmentService::getLastSegmentPosition() == $this->position;
    }

    public function handle()
    {
        if (!$this->model->id) {
            AccountSegmentService::createSegmentValue(new CreateSegmentValueDto([
                'segment_definition_id' => AccountSegment::getByPosition($this->position)->id,
                'segment_value' => request('segment_value'),
                'segment_description' => request('segment_description'),
                'account_type' => $this->isRealAccount ? (int) request('account_type') : null,
                'allow_manual_entry' => request('allow_manual_entry', true),
            ]));
        } else {
            AccountSegmentService::updateSegmentValue(new UpdateSegmentValueDto([
                'id' => $this->model->id,
                'segment_description' => request('segment_description'),
                'account_type' => $this->isRealAccount ? (int) request('account_type') : null,
                'allow_manual_entry' => request('allow_manual_entry', true),
            ]));
        }
    }

    public function body()
    {
        $segment = AccountSegment::getByPosition($this->position);

        if (!$segment) {
            return _Html('finance-invalid-segment-position');
        }

        $segmentLenght = $segment->segment_length;

        return _Rows(
            $this->model->id ? null : _Rows(
                _CardGray200(
                    _Html(__('finance-with-value-example-account-value', [
                        'example' =>
                        str_pad('', $segmentLenght, 'X'),
                    ])),
                )->p4(),
                _ValidatedInput('finance-account-value')->name('segment_value')->allow("^[0-9]{0,$segmentLenght}$")
                    ->required(),
            ),
            _Input('finance-account-segment-description')
                ->name('segment_description')
                ->required(),
            !$this->isRealAccount ? null : _Select('finance-account-type')->name('account_type')
                ->options(AccountTypeEnum::optionsWithLabels())
                ->required(),
            _Toggle('finance-allow-manual-entry')
                ->name('allow_manual_entry')
                ->default(true)
                ->class('!mb-0 mt-2'),
        );
    }

    public function headerButtons()
    {
        return $this->hasSubmitButton ? _SubmitButton('general.save')->closeModal()->refresh(['segments-values-page', 'finance-chart-of-accounts']) : null;
    }

    public function rules()
    {
        return [

        ];
    }
}
