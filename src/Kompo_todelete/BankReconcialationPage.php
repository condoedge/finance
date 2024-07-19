<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Conciliation;
use Condoedge\Finance\Models\Entry;
use App\View\Form;
use App\View\Reports\BaseExportableReport;
use Illuminate\Support\Carbon;

class BankReconcialationPage extends Form
{
    public $model = Conciliation::class;

    public $containerClass = 'container';

    protected $panelId = 'reconciliation-entries-panel';

    public function completed()
    {
        $this->model->calculateAmountsFromEntries();
    }

    public function response()
    {
        return redirect()->route('conciliation.page', [
            'id' => $this->model->id,
        ]);
    }

    public function render()
    {
        $lastConciliation = Conciliation::where('account_id', $this->model->account_id)->where('start_date', '<', $this->model->start_date)->orderByDesc('start_date')->first();

        return _Rows(
            _FlexBetween(
                _Breadcrumbs(
                    _Link('finance.all-reconciliations')->href('conciliations.table'),
                    _Html('finance.reconciliation'),
                ),
                _SubmitButton('finance.save-reload-transactions'),
            )->class('mb-4'),
            _FlexBetween(
                $this->model->account->getOptionLabel(),
                static::labelInput('Period', _Html($this->model->start_date?->translatedFormat('F Y'))),
                static::labelInput('finance.reconciliation-date', _Date()->name('reconciled_at')->default(date('Y-m-d'))),
            )->class('dashboard-card p-4 flex-wrap'),
            _Columns(
                _Rows(
                    _Rows(
                        static::labelInput(
                            'finance.statement-opening-balance',
                            _DollarInput()->name('opening_balance')->id('opening_balance_input')->run('checkReconciliationAmount')
                                ->default($lastConciliation?->closing_balance),
                        ),
                        static::labelInput(
                            'finance.statement-closing-balance',
                            _DollarInput()->name('closing_balance')->id('closing_balance_input')->run('checkReconciliationAmount'),
                        ),
                    )->class('dashboard-card p-4 space-y-4'),
                ),
                _Rows(
                    _Rows(
                        static::labelInput(
                            __('finance.resolved'),
                            _DollarInput()->name('resolved')->readOnly()->id('recon_resolved'),
                        )->class('mb-4'),
                        static::labelInput(
                            __('finance.remaining'),
                            _DollarInput()->name('remaining')->readOnly()->id('recon_remaining'),
                        )
                    )->class('card-gray-200 p-4')
                )
            ),
            _Flexcenter(
                new BankReconcialationEntries([
                    'conciliation_id' => $this->model->id,
                ])
            )
        );
    }

    protected static function labelInput($label, $input)
    {
        return _FlexBetween(
            static::labelOnly($label),
            $input->class('w-48 mb-0 ml-4'),
        );
    }

    protected static function labelOnly($label)
    {
        return _Html($label)->class('font-semibold text-greenmain');;
    }

    public function rules()
    {
        return [
            'reconciled_at' => 'required',
        ];
    }

    public function js()
    {
        return file_get_contents(resource_path('views/scripts/finance.js')).';'.
            BaseExportableReport::getReportJs(__('finance.reconciliation'));
    }
}
