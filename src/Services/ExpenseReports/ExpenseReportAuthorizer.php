<?php

namespace Condoedge\Finance\Services\ExpenseReports;

use Condoedge\Finance\Models\ExpenseReport;
use Kompo\Auth\Models\Teams\PermissionTypeEnum;

/**
 * Who may decide on an expense report. Both checks answer for the REPORT's team, never the
 * acting one — a user holds roles in several teams at once and the current one is rarely
 * the report's.
 *
 * Explicit on purpose: the model's write scope cannot carry the rule.
 * SecurityBypassService::hasBypassByUserId() drops that scope entirely when `user_id`
 * matches the authenticated user, which let a submitter approve and pay their own report.
 */
class ExpenseReportAuthorizer
{
    public function approvePermission(): string
    {
        return config('kompo-finance.expense_reports.approve_permission') ?: 'ExpenseReport';
    }

    /**
     * Null leaves paying on the approval right — the behavior before the split. An app that
     * reserves payment for a treasurer names its own key in the config.
     */
    public function paymentPermission(): string
    {
        return config('kompo-finance.expense_reports.payment_permission') ?: $this->approvePermission();
    }

    public function canApprove(ExpenseReport $expenseReport): bool
    {
        return $this->hasWriteOnReportTeam($this->approvePermission(), $expenseReport);
    }

    public function canMarkAsPaid(ExpenseReport $expenseReport): bool
    {
        return $this->hasWriteOnReportTeam($this->paymentPermission(), $expenseReport);
    }

    public function assertCanApprove(ExpenseReport $expenseReport): void
    {
        abort_if(!$this->canApprove($expenseReport), 403, __('error-expense-report-approval-not-allowed'));
    }

    public function assertCanMarkAsPaid(ExpenseReport $expenseReport): void
    {
        abort_if(!$this->canMarkAsPaid($expenseReport), 403, __('error-expense-report-payment-not-allowed'));
    }

    /**
     * A report with no team has no one to answer to, so nobody may decide on it.
     * The resolver walks ancestors, so a holder above the team covers the teams below.
     */
    protected function hasWriteOnReportTeam(string $permissionKey, ExpenseReport $expenseReport): bool
    {
        if (!$expenseReport->team_id) {
            return false;
        }

        return auth()->user()?->hasPermission(
            $permissionKey,
            PermissionTypeEnum::WRITE,
            [$expenseReport->team_id],
        ) ?? false;
    }
}
