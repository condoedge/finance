<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Recurrence;
use Kompo\Table;

class BillsRecurringTable extends Table
{
    public function query()
    {
        return Recurrence::where('union_id', currentUnion()->id)->where('child_type', Recurrence::CHILD_BILL);
    }

    public function top()
    {
        return _FlexBetween(
            _PageTitle('finance.recurring-bills')->class('mb-4'),
            _Link('finance.create-recurring-bill')->icon(_Sax('add',20))->button()->class('mb-4')
                ->href('bills-recurring.form')
        );
    }

    public function headers()
    {
        return [
            _Th('Status'),
            _Th('Supplier'),
            _Th('Schedule'),
            _Th('finance.next-bill'),
            _Th('Amount')->class('text-right'),
        ];
    }

    public function render($recurrence)
    {
        $mainBill = $recurrence->getMainBill();
        $nextBill = $recurrence->getNextBill();

        $supplierName = ($mainBill ?: $nextBill)?->supplier->display;

    	$tableRow = _TableRow(
            $recurrence->getStatusPill(),
            _Html($supplierName),
            _Rows(
                _Html($recurrence->schedule_label)->class('text-level1 font-medium'),
                _Html($recurrence->schedule_frame)->class('text-xs text-gray-600'),
            ),
            _Html($nextBill?->billed_at->translatedFormat('d M Y')),
            _Currency($nextBill?->amount)->class('text-right'),
        );

        if ($mainBill) {
            $tableRow = $tableRow->href('bills-recurring.form', [
                'id' => $mainBill->id,
            ]);
        }

        return $tableRow;
    }
}
