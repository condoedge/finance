<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Casts\SafeDecimalCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Kompo\Auth\Facades\TeamModel;
use Kompo\Auth\Facades\UserModel;
use Kompo\Auth\Models\Teams\BelongsToTeamTrait;
use Kompo\Auth\Contracts\Security\ScopedToTeam;
use Kompo\Auth\Contracts\Security\HasOwnedRecords;

/**
 *  * Represents an expense report in the finance module.
 *
 * @property int $id
 * @property int $user_id
 * @property int $customer_id
 * @property int $team_id
 * @property bool $is_draft
 * @property string $expense_title
 * @property string $expense_description
 * @property string|null $review_note Rejection reason or optional approval comment.
 * @property ExpenseReportStatusEnum $expense_status
 * @property SafeDecimal $amount_before_taxes @CALCULATED by `calculate_expense_report_amount_before_taxes`
 * @property SafeDecimal $total_amount @CALCULATED by `calculate_total_expense_report_amount`
 */
class ExpenseReport extends AbstractMainFinanceModel implements ScopedToTeam, HasOwnedRecords
{
    use \Kompo\Auth\Models\Concerns\Security\OwnedByUserIdColumn;
    use \Kompo\Auth\Models\Concerns\Security\BelongsToOneTeam;
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

    public function team()
    {
        return $this->belongsTo(TeamModel::getClass())->withTrashed();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'expense_report_id');
    }

    public function approve(?string $note = null)
    {
        if ($this->expense_status !== ExpenseReportStatusEnum::PENDING) {
            abort(403, __('error-expense-report-status-not-pending'));
        }

        $this->expense_status = ExpenseReportStatusEnum::APPROVED;
        $this->review_note = $note !== null && trim($note) !== '' ? $note : null;
        $this->save();
    }

    public function reject(string $note)
    {
        if ($this->expense_status !== ExpenseReportStatusEnum::PENDING) {
            abort(403, __('error-expense-report-status-not-pending'));
        }

        if (trim($note) === '') {
            abort(422, __('error-expense-report-reject-reason-required'));
        }

        $this->expense_status = ExpenseReportStatusEnum::REJECTED;
        $this->review_note = $note;
        $this->save();
    }

    public function markAsPaid()
    {
        if ($this->expense_status !== ExpenseReportStatusEnum::APPROVED) {
            abort(403, __('error-expense-report-only-approved-can-be-paid'));
        }

        $this->expense_status = ExpenseReportStatusEnum::PAID;
        $this->save();
    }

    public static function columnsIntegrityCalculations()
    {
        return [
            'amount_before_taxes' => DB::raw('calculate_expense_report_amount_before_taxes(fin_expense_reports.id)'),
            'total_amount' => DB::raw('calculate_total_expense_report_amount(fin_expense_reports.id)'),
        ];
    }
}
