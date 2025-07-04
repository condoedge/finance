<?php

namespace Condoedge\Finance\Kompo\SegmentManagement;

use Condoedge\Finance\Enums\SegmentDefaultHandlerEnum;
use Condoedge\Finance\Facades\AccountSegmentService;
use Condoedge\Finance\Models\AccountSegment;
use Kompo\Form;

class SegmentStructureFormModal extends Form
{
    public $model = AccountSegment::class;

    protected $isEditMode = false;
    protected $maxPosition;
    protected $isLastSegment = false;

    public function created()
    {
        if ($this->model->id) {
            $this->isEditMode = true;
            // Check if this is the last segment
            $this->isLastSegment = $this->model->segment_position === AccountSegment::max('segment_position');
        }

        // Get the next available position
        $this->maxPosition = AccountSegment::max('segment_position') ?? 0;
    }

    public function handle()
    {
        AccountSegmentService::createOrUpdateSegment(new \Condoedge\Finance\Models\Dto\Gl\CreateOrUpdateSegmentDto([
            'id' => $this->model->id,
            'segment_description' => request('segment_description'),
            'segment_position' => request('segment_position'),
            'segment_length' => request('segment_length'),
            'default_handler' => request('default_handler'),
            'default_handler_config' => request('default_handler_config'),
        ]));
    }

    public function render()
    {
        return _Modal(
            _ModalHeader(
                _Title(
                    $this->isEditMode ?
                    __('finance-edit-segment-structure') :
                    __('finance-add-segment-structure')
                ),
                _SubmitButton('general.save')
            ),
            _ModalBody(
                _Input('finance-segment-description')
                    ->name('segment_description')
                    ->placeholder('finance-eg-department-project-account')
                    ->maxlength(255)
                    ->required(),
                _Input('finance-segment-position')
                    ->name('segment_position')
                    ->selfPost('renderStructurePreview')->withAllFormValues()->inPanel('segments-structure-preview')
                    ->type('number')
                    ->min(1)
                    ->max(10)
                    ->default($this->maxPosition + 1)
                    ->required(),
                _Input('finance-segment-length')
                    ->name('segment_length')
                    ->selfPost('renderStructurePreview')->withAllFormValues()->inPanel('segments-structure-preview')
                    ->type('number')
                    ->min(1)
                    ->max(10)
                    ->required(),

                // Default handler selection
                _Select('finance-default-handler')
                    ->name('default_handler')
                    ->options(
                        collect(SegmentDefaultHandlerEnum::cases())
                            ->mapWithKeys(fn ($handler) => [$handler->value => $handler->label()])
                            ->toArray()
                    )
                    ->placeholder(__('finance.manual-entry-default'))
                    ->selfPost('renderHandlerConfig')->withAllFormValues()->inPanel('handler-config-panel'),

                // Handler configuration panel
                _Panel(
                    $this->renderHandlerConfig()
                )->id('handler-config-panel'),

                // Show current structure preview
                _Panel(
                    $this->renderStructurePreview(),
                )->id('segments-structure-preview'),
                _SubmitButton('save')->closeModal()->refresh('segments-table'),
            )
        )->class('max-w-lg');
    }

    /**
     * Render structure preview
     */
    public function renderStructurePreview()
    {
        $segments = AccountSegment::getAllOrdered();

        if ($segments->isEmpty() && !$this->isEditMode) {
            return null;
        }

        // Build preview including the new/edited segment
        $preview = [];
        $inserted = false;

        foreach ($segments as $segment) {
            if ($this->isEditMode && $segment->id === $this->model->id) {
                // Show edited segment
                $preview[] = str_repeat('X', request('segment_length', $segment->segment_length));
            } elseif (!$this->isEditMode && !$inserted && $segment->segment_position > request('segment_position', $this->maxPosition + 1)) {
                // Insert new segment in correct position
                $preview[] = str_repeat('X', request('segment_length', 2));
                $preview[] = str_repeat('X', $segment->segment_length);
                $inserted = true;
            } else {
                $preview[] = str_repeat('X', $segment->segment_length);
            }
        }

        // Add new segment at end if not inserted
        if (!$this->isEditMode && !$inserted) {
            $preview[] = str_repeat('X', request('segment_length', 2));
        }

        return _Card(
            _TitleMini('finance-account-format-preview')->class('mb-2'),
            _Html(implode('-', $preview))->class('font-mono text-lg text-center')
        )->class('mt-4 p-3 bg-gray-50');
    }

    /**
     * Render handler-specific configuration fields
     */
    public function renderHandlerConfig()
    {
        $handler = request('default_handler') ?: $this->model->default_handler;

        if (!$handler) {
            return null;
        }

        $handlerEnum = SegmentDefaultHandlerEnum::tryFrom($handler);
        if (!$handlerEnum || !$handlerEnum->requiresConfig()) {
            return null;
        }

        return _Rows(
            match($handler) {
                SegmentDefaultHandlerEnum::SEQUENCE->value => $this->renderSequenceConfig(),
                SegmentDefaultHandlerEnum::FIXED_VALUE->value => $this->renderFixedValueConfig(),
                default => null
            }
        )->class('mt-4 p-4 bg-gray-50 rounded-lg');
    }

    /**
     * Render sequence handler configuration
     */
    protected function renderSequenceConfig()
    {
        return _Rows(
            _Input('finance-prefix')
                ->name('default_handler_config[prefix]')
                ->placeholder(__('finance.optional-prefix-eg-gl'))
                ->default($this->model->default_handler_config['prefix'] ?? '')
                ->maxlength(5),
            _Select('finance-sequence-scope')
                ->name('default_handler_config[sequence_scope]')
                ->options([
                    'global' => __('finance.global-sequence'),
                    'team' => __('finance.per-team-sequence'),
                    'parent_team' => __('finance.per-parent-team-sequence'),
                ])
                ->default($this->model->default_handler_config['sequence_scope'] ?? 'team')
                ->required(),
            _Input('finance-start-value')
                ->name('default_handler_config[start_value]')
                ->type('number')
                ->min(0)
                ->default($this->model->default_handler_config['start_value'] ?? 1)
                ->placeholder(__('finance.starting-number'))
        )->class('gap-4');
    }

    /**
     * Render fixed value configuration
     */
    protected function renderFixedValueConfig()
    {
        return _Input('finance-fixed-value')
            ->name('default_handler_config[value]')
            ->placeholder(__('finance.enter-fixed-value'))
            ->default($this->model->default_handler_config['value'] ?? '')
            ->maxlength($this->model->segment_length ?: 10)
            ->required();
    }

    public function rules()
    {
        $rules = [
            'segment_description' => ['required', 'string', 'max:255'],
            'segment_position' => 'required|integer|min:1|max:10|unique:fin_account_segments,segment_position' .
                ($this->isEditMode ? ',' . $this->model->id : ''),
            'segment_length' => 'required|integer|min:1|max:10',
            'default_handler' => 'nullable|string|in:' . collect(SegmentDefaultHandlerEnum::cases())->pluck('value')->implode(','),
        ];

        return $rules;
    }
}
