<?php

namespace Condoedge\Finance\Kompo\SegmentManagement;

use Condoedge\Finance\Models\SegmentValue;
use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Facades\AccountSegmentService;
use Condoedge\Finance\Models\AccountTypeEnum;
use Condoedge\Finance\Models\Dto\Gl\CreateSegmentValueDto;
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
        AccountSegmentService::createSegmentValue(new CreateSegmentValueDto([
            'segment_definition_id' => AccountSegment::getByPosition($this->position)->id,
            'segment_value' => request('segment_value'),
            'segment_description' => request('segment_description'),
            'account_type' => $this->isRealAccount ? (int) request('account_type') : null,
        ]));
    }
    
    public function body()
    {
        $segment = AccountSegment::getByPosition($this->position);
        
        if (!$segment) {
            return _Html('finance-invalid-segment-position');
        }

        $segmentLenght = $segment->segment_length;

        return _Rows(
            _CardGray200(
                _Html(__('finance-example-account-value', ['example' => 
                    str_pad('', $segmentLenght, 'X'),
                ])),
            )->p4(),

            _ValidatedInput('finance-account-value')->name('segment_value')->allow("^[0-9]{0,$segmentLenght}$")
                ->required(),

            _Input('finance-account-segment-description')
                ->name('segment_description')
                ->required(),

            !$this->isRealAccount ? null : _Select('finance-account-type')->name('account_type')
                ->options(AccountTypeEnum::optionsWithLabels())
                ->required()
        );
    }
    
    public function headerButtons()
    {
        return $this->hasSubmitButton ? _SubmitButton('general.save')->closeModal()->refresh(['segments-values-page', 'finance-chart-of-accounts']) : null;
    }

    public function rules()
    {
        return [
            'segment_value' => 'required|string',
            'segment_description' => 'required|string|max:255',
        ];
    }
}
