<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Casts\SafeDecimalCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Kompo\Auth\Facades\UserModel;
use Kompo\Auth\Models\Teams\BelongsToTeamTrait;

/**
 *  * Represents an expense report in the finance module.
 *
 * @property int $id
 * @property int $user_id
 * @property int $customer_id
 * @property int $team_id
 * 
 * @property bool $is_draft
 * @property string $expense_title
 * @property string $expense_description
 * @property ExpenseReportStatusEnum $expense_status
 * @property SafeDecimal $amount_before_taxes @CALCULATED by `calculate_expense_report_amount_before_taxes`
 * @property SafeDecimal $total_amount @CALCULATED by `calculate_total_expense_report_amount`
 */
class ExpenseReport extends AbstractMainFinanceModel
{
    use HasFactory;
    use BelongsToTeamTrait;

    protected $table = 'fin_expense_reports';

    protected $casts = [
        'expense_status' => ExpenseReportStatusEnum::class,
        'amount_before_taxes' => SafeDecimalCast::class,
        'total_amount' => SafeDecimalCast::class,
    ];

    public function user()
    {
        return $this->belongsTo(UserModel::getClass());
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'expense_report_id');
    }

    public static function columnsIntegrityCalculations()
    {
        return [
            'amount_before_taxes' => DB::raw('calculate_expense_report_amount_before_taxes(fin_expense_reports.id)'),
            'total_amount' => DB::raw('calculate_total_expense_report_amount(fin_expense_reports.id)'),
        ];
    }
}
