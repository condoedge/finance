<?php

namespace Condoedge\Finance\Kompo\SegmentManagement;

use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Models\SegmentValue;
use Condoedge\Utils\Kompo\Common\Table;

class SegmentsValuesPage extends Table
{
    public $id = 'segments-values-page';

    protected $selectedSegmentPosition;

    public function created()
    {
        $this->selectedSegmentPosition = request('segment_position') ?? $this->prop('segment_position');

        $this->prop(['segment_position' => $this->selectedSegmentPosition]);
    }

    public function query()
    {
        if (!$this->selectedSegmentPosition) {
            return [];
        }

        return SegmentValue::getForPosition($this->selectedSegmentPosition, false);
    }

    public function top()
    {
        $segments = AccountSegment::getAllOrdered();

        if ($segments->isEmpty()) {
            return _CardWarning(
                _FlexBetween(
                    _Html('finance-define-segment-structure-first'),
                    _Link('finance-go-to-structure')
                        ->href('finance.segment-manager', ['tab' => 'structure'])
                )->class('w-full gap-4'),
            );
        }

        return _Select('finance-select-segment-to-manage')->name('segment_position', false)
            ->options($segments->pluck('segment_description', 'segment_position')->map(
                fn ($desc, $position) => _Html($desc)->attr(['data-segment-position' => $position])
            ))
            ->value($this->selectedSegmentPosition)
            ->run('() => {
                ('. getPushParameterFn('segment_position', "$('.vlCustomLabel > div[data-segment-position]').data().segmentPosition", true) .')()
                utils.setLoadingScreen();
                location.reload();
            }');
    }

    public function headers()
    {
        return [
            _Th('finance-segment-value')->class('w-1/4'),
            _Th('finance-segment-description')->class('w-1/4'),
            _Th('finance-status'),
            _Th('finance-accounts'),
            _Th('finance-actions')->class('w-8'),
        ];
    }

    public function render($value)
    {
        return _TableRow(
            _Html($value->segment_value)->class('font-mono font-bold'),
            _Html($value->segment_description),
            _Rows(
                $value->is_active ?
                    _Pill(__('finance-active'))->class('bg-positive text-white') :
                    _Pill(__('finance-inactive'))->class('bg-gray-200 text-gray-600')
            ),
            _Html($value->getUsageCount() . ' ' . __('finance-accounts')),
            _Rows(
                _FlexEnd(
                    _Link()->icon('pencil')
                        ->selfGet('getSegmentValueFormModal', [
                            'value_id' => $value->id
                        ])->inModal(),
                    _Link()->icon($value->is_active ? 'ban' : 'check')
                        ->selfPost('toggleSegmentValueStatus', [
                            'value_id' => $value->id
                        ])->refresh(),
                    _Delete($value),
                )->class('space-x-2')
            )->class('text-right')
        );
    }

    public function toggleSegmentValueStatus($valueId)
    {
        $value = SegmentValue::findOrFail($valueId);
        $value->is_active = !$value->is_active;
        $value->save();
    }

    /**
     * Get segment value form modal
     */
    public function getSegmentValueFormModal($valueId = null)
    {
        return new SegmentValueFormModal($valueId);
    }
}
