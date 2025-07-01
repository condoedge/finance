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

    protected $isViewOnly = false;
    protected $teamId;

    public function created()
    {
        $this->teamId = currentTeamId();
        $this->isViewOnly = $this->model->exists && $this->model->is_posted;
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
                    $this->model->exists ?
                        'translate.finance-gl-transaction' . ' #' . $this->model->gl_transaction_number :
                        'translate.finance-create-gl-transaction'
                ),
                _Link('translate.finance-back-to-list')
                    ->icon('arrow-left')
                    ->href(route('finance.gl.gl-transactions'))
            )->class('mb-6'),

            // Posted warning
            $this->isViewOnly ?
                _Alert('translate.finance-posted-transaction-readonly')->warning() : null,

            // Header information
            _Card(
                _Columns(
                    _Date('translate.finance-fiscal-date')
                        ->name('fiscal_date')
                        ->required()
                        ->disabled($this->isViewOnly)
                        ->default(now()->format('Y-m-d')),

                    _Select('translate.finance-transaction-type')
                        ->name('gl_transaction_type')
                        ->options(GlTransactionTypeEnum::optionsWithLabels())
                        ->disabled($this->isViewOnly)
                        ->default(GlTransactionTypeEnum::MANUAL_GL)
                        ->required(),
                )->class('gap-4 mb-4'),

                _Textarea('translate.finance-description')
                    ->name('transaction_description')
                    ->rows(2)
                    ->disabled($this->isViewOnly)
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

            // // Actions
            // !$this->isViewOnly ?
            //     _FlexEnd(
            //         _SubmitButton('translate.finance-save-draft')
            //             ->outlined(),
            //         _SubmitButton('translate.finance-save-and-post'),
            //     )->class('mt-4 gap-3') : null,

            _SubmitButton('translate.finance-save'),
        );
    }

    public function js()
    {
        return <<<javascript
// Update totals when lines change
function updateTotals() {
    let totalDebits = 0;
    let totalCredits = 0;
    
    // Find all debit inputs
    document.querySelectorAll('input[name$="[debit_amount]"]').forEach(input => {
        totalDebits += parseFloat(input.value) || 0;
    });
    
    // Find all credit inputs
    document.querySelectorAll('input[name$="[credit_amount]"]').forEach(input => {
        totalCredits += parseFloat(input.value) || 0;
    });
    
    // Update display
    document.getElementById('total-debits').textContent = totalDebits.toFixed(2);
    document.getElementById('total-credits').textContent = totalCredits.toFixed(2);
    
    const difference = Math.abs(totalDebits - totalCredits);
    const differenceEl = document.getElementById('total-difference');
    differenceEl.textContent = difference.toFixed(2);
    
    // Highlight if not balanced
    if (difference > 0.01) {
        differenceEl.classList.add('text-danger');
        differenceEl.classList.remove('text-success');
    } else {
        differenceEl.classList.add('text-success');
        differenceEl.classList.remove('text-danger');
    }
}

// Update totals on input change
document.addEventListener('input', function(e) {
    if (e.target.name && (e.target.name.includes('debit_amount') || e.target.name.includes('credit_amount'))) {
        updateTotals();
    }
});

// Initial calculation
setTimeout(updateTotals, 100);

// Update when rows are added/removed
document.addEventListener('rowAdded', updateTotals);
document.addEventListener('rowRemoved', updateTotals);
javascript;
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
