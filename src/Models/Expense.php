<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Casts\SafeDecimalCast;
use Condoedge\Utils\Models\Files\MorphManyFilesTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Expense extends AbstractMainFinanceModel
{
    use MorphManyFilesTrait;
    use HasFactory;

    protected $table = 'fin_expenses';

    protected $casts = [
        'expense_type' => ExpenseReportTypeEnum::class,
        'expense_date' => 'datetime:Y-m-d',
        'expense_amount_before_taxes' => SafeDecimalCast::class,
        'total_expense_amount' => SafeDecimalCast::class,
    ];

    public function expenseReport()
    {
        return $this->belongsTo(ExpenseReport::class);
    }
}
