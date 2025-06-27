<?php

namespace Condoedge\Finance\Kompo\ChartOfAccounts;

use Condoedge\Finance\Kompo\ChartOfAccounts\AccountsList;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Facades\AccountSegmentService;
use Condoedge\Finance\Kompo\SegmentManagement\SegmentValueFormModal;
use Condoedge\Finance\Models\AccountTypeEnum;
use Condoedge\Utils\Kompo\Common\Form;

class ChartOfAccounts extends Form
{
    public $class = 'max-w-6xl mx-auto';

    protected $accountType;
    protected $segmentStructure;
    protected $lastSegmentDefinition;

    public $id = 'finance-chart-of-accounts';

    public function created()
    {
        $this->accountType = $this->prop('account_type') ?: 'all';

        $this->segmentStructure = AccountSegmentService::getSegmentStructure();
        $this->lastSegmentDefinition = $this->segmentStructure->last();

        AccountSegmentService::createDefaultSegments();
    }

    public function render()
    {
        return _Rows(
            _Html('translate.finance-chart-of-accounts')
                ->class('text-2xl font-bold mb-4'),

            $this->renderSegmentStructureInfo(),

            _FlexEnd(
                _Button('translate.create-account')->class('mb-2')
                    ->selfGet('getLastSegmentValueForm')->inModal(),
            ),

            $this->renderAccountTypeTabs(),

            new AccountsList([
                'account_type' => $this->accountType,
            ])
        );
    }

    public function getLastSegmentValueForm()
    {
        return new SegmentValueFormModal();
    }

    /**
     * Render segment structure information
     */
    protected function renderSegmentStructureInfo()
    {
        $formatExample = AccountSegmentService::getAccountFormatMask();

        return _Card(
            _Rows(
                _Html('finance-account-format')->class('text-sm text-gray-600'),
                _Html($formatExample)->class('font-bold pr-2 border-r border-gray-300'),
                _Flex(collect($this->segmentStructure)->map(
                    fn($seg) =>
                    _Html("{$seg->segment_description} ({$seg->segment_length})")
                        ->class('text-xs text-gray-500')
                ))->class('gap-3'),
            ),

            _Link('translate.go-to-definition')->button()
                ->href('finance.segment-manager'),

        )->class('mb-4 p-3 bg-blue-50 border-blue-200 justify-between items-center flex-row');
    }

    /**
     * Render account type filter tabs
     */
    protected function renderAccountTypeTabs()
    {
        $accountTypes = AccountTypeEnum::optionsWithLabels();

        $accountTypes->prepend(__('translate.finance-all-accounts'), 'all');

        return _Flex(
            collect($accountTypes)->map(
                fn($label, $value) => _TabLink($label, $this->accountType == $value)
                    ->href('finance.chart-of-accounts', [
                        'account_type' => $value,
                    ])
            )
        )->class('mb-4 border-b');
    }
}
