<?php

namespace Condoedge\Finance\Kompo\GlTransactions;

use Condoedge\Finance\Enums\GlTransactionTypeEnum;
use Condoedge\Finance\Models\GlTransactionHeader;
use Condoedge\Finance\Models\Dto\Gl\CreateGlTransactionDto;
use Condoedge\Finance\Models\SegmentValue;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Services\GlTransactionServiceInterface;
use WendellAdriel\ValidatedDTO\Exceptions\ValidatedDTOException;
use Condoedge\Utils\Kompo\Common\Form;

class GlTransactionForm extends Form
{
    public $model = GlTransactionHeader::class;
    protected $teamId;

    public function created()
    {
        $this->teamId = currentTeamId();
    }

    /**
     * Handle form submission using service and DTOs
     */
    public function handle(GlTransactionServiceInterface $glTransactionService)
    {
        $requestData = parseDataWithMultiForm('lines');

        // Create new transaction
        // DTO constructor will validate automatically, including balance check in after() method
        $dto = new CreateGlTransactionDto([
            'lines' => $requestData['lines'] ?? [],
            'fiscal_date' => $requestData['fiscal_date'] ?? now()->format('Y-m-d'),
            'gl_transaction_type' => (int) $requestData['gl_transaction_type'] ?? GlTransactionTypeEnum::MANUAL_GL->value,
            'transaction_description' => $requestData['transaction_description'] ?? '',
            'team_id' => $this->teamId,
        ]);

        $this->model = $glTransactionService->createTransaction($dto);
        $glTransactionService->postTransaction($this->model);
        
        return redirect()->route('finance.gl.gl-transactions');
    }

    public function render()
    {
        return _Rows(
            // Header
            _FlexBetween(
                _TitleMain(
                    'translate.finance-manage-gl-transaction'
                ),
                _Link('translate.finance-back-to-list')
                    ->icon('arrow-left')
                    ->href('finance.gl.gl-transactions')
            )->class('mb-6'),

            // Header information
            _Card(
                _Columns(
                    _Date('translate.finance-fiscal-date')
                        ->name('fiscal_date')
                        ->required()
                        ->default(now()->format('Y-m-d')),

                    _Select('translate.finance-transaction-type')
                        ->name('gl_transaction_type')
                        ->options(GlTransactionTypeEnum::optionsWithLabels())
                        ->default(GlTransactionTypeEnum::MANUAL_GL)
                        ->required(),
                )->class('gap-4 mb-4'),

                _Textarea('translate.finance-description')
                    ->name('transaction_description')
                    ->required(),
            )->class('mb-4'),

            // Transaction lines
            _Card(
                _TitleMini('translate.finance-transaction-lines')->class('mb-4'),
                
                // Totals
                _MultiForm()
                    ->noLabel()
                    ->name('lines')
                    ->formClass(GlTransactionLineForm::class, [
                        'team_id' => $this->teamId,
                        'transaction_type' => $this->model->gl_transaction_type ?? 1,
                    ])
                    ->asTable([
                        __('translate.finance-account'),
                        __('translate.finance-description'),
                        __('translate.finance-debit'),
                        __('translate.finance-credit'),
                        '', // Actions
                    ])
                    ->addLabel(__('translate.finance-add-line'))
                    ->class('mb-6')
                    ->id('gl-transaction-lines'),
            ),

            $this->model->id ? null : _SubmitButton('translate.finance-save')->redirect(),
        );
    }

    public function rules()
    {
        return [
            'fiscal_date' => 'required|date',
            'gl_transaction_type' => 'required|integer|between:1,4',
            'transaction_description' => 'required|string|max:500',
        ];
    }
}
