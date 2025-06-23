<?php

namespace Condoedge\Finance\Kompo\SegmentManagement;

use Condoedge\Finance\Models\SegmentValue;
use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Facades\AccountSegmentService;
use Condoedge\Finance\Models\AccountTypeEnum;
use Condoedge\Utils\Kompo\Common\Modal;

class SegmentValueFormModal extends Modal
{
    public $_Title = 'translate.create-account-segment-value';
    public $model = SegmentValue::class;

    protected $position;
    
    public function created()
    {
        $this->position = $this->model->segmentDefinition?->segment_position ?: $this->prop('segment_position') ?: AccountSegmentService::getLastSegmentPosition();
    }

    public function beforeSave()
    {
        $this->model->segment_definition_id = AccountSegment::getByPosition($this->position)->id;
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
                _Html(__('translate.example-account-value', ['example' => 
                    str_pad('', $segmentLenght, 'X'),
                ])),
            )->p4(),

            _ValidatedInput('translate.account-value')->name('segment_value')->allow("^[0-9]{0,$segmentLenght}$")
                ->placeholder('Enter segment value')
                ->required(),

            _Input('translate.account-segment-description')
                ->name('segment_description')
                ->placeholder('Select segment description')
                ->required(),

            AccountSegmentService::getLastSegmentPosition() != $this->position ? null : _Select('account-type')->name('account_type')
                ->options(AccountTypeEnum::optionsWithLabels())
                ->placeholder('Select account type')
                ->required()
        );
    }
    
    public function headerButtons()
    {
        return $this->hasSubmitButton ? _SubmitButton('general.save')->closeModal()->refresh('segments-values-page') : null;
    }

    public function rules()
    {
        return [
            'segment_value' => 'required|string',
            'segment_description' => 'required|string|max:255',
        ];
    }
}
