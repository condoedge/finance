<?php

namespace Condoedge\Finance\Models;

use App\Models\Condo\Unit;
use Condoedge\Finance\Models\Fund;
use Condoedge\Finance\Models\Invoice;
use Kompo\Auth\Models\Model;
use App\Models\Traits\MorphManyNotifications;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class Budget extends Model
{
    use \Kompo\Auth\Models\Teams\BelongsToTeamTrait;

    use MorphManyNotifications;

    protected $casts = [
        'fiscal_year_start' => 'datetime'
    ];

    public const STATUS_DRAFT = 1;
    public const STATUS_APPROVED = 2;
    public const STATUS_COMPLETED = 3;

    /* RELATIONSHIPS */
    public function budgetDetails()
    {
        return $this->hasMany(BudgetDetail::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /* SCOPES */
    public function scopeApproved($query)
    {
        $query->where('status', static::STATUS_APPROVED);
    }

    /* CALCULATED FIELDS */
    public static function statuses()
    {
        return [
            static::STATUS_DRAFT => __('finance.draft'),
            static::STATUS_APPROVED => __('finance.approved'),
            static::STATUS_COMPLETED => __('finance.completed'),
        ];
    }

    public function isDraft()
    {
        return $this->status == static::STATUS_DRAFT;
    }

    public function isApproved()
    {
        return $this->status == static::STATUS_APPROVED;
    }

    public function hasFundInvoiced($fundId)
    {
        return $this->isApproved() && $this->budgetDetails()->where('fund_id', $fundId)->whereNotNull('included_at')->count();
    }

    public function addedFundsAdhoc()
    {
        return !$this->isApproved() ? collect([]) :
            $this->budgetDetails()->whereNull('included_at')->where('amount','<>',0)->with('fund')->get()
                ->map(fn($bd) => $bd->fund)->unique(fn($fund) => $fund->id);
    }

    public function getMonthOfContributionDate($contributionDate)
    {
        return $this->getContributionDates()->search($contributionDate);
    }

    public function getContributionDates()
    {
        return static::createContributionDates($this->fiscal_year_start);
    }

    public static function createContributionDates($startDate)
    {
        return collect([1,2,3,4,5,6,7,8,9,10,11,12])->mapWithKeys(fn($i) => [
            $i => carbon($startDate)->copy()->addMonths($i - 1)
        ]);
    }

    public function getInvoiceDates()
    {
        return $this->invoices()->distinct('invoiced_at')->orderBy('invoiced_at')->pluck('invoiced_at');
    }

    public function getRemainingDates($fromDate = null)
    {
        return $this->getContributionDates()->filter(
            fn($date) => static::acceptDate($date, $fromDate)
        );
    }

    public function getMissingDates($fromDate = null)
    {
        return $this->getContributionDates()->reject(
            fn($date) => static::acceptDate($date, $fromDate)
        );
    }

    public function getMissingAndEmptyDates($fromDate = null)
    {
        $lastYearBudget = $this->getPreviousBudget();

        return $this->getMissingDates($fromDate)->reject(
            fn($date) => $this->hasInvoicesForDate($date) || ($lastYearBudget ? $lastYearBudget->hasInvoicesForDate($date) : false)
        );
    }

    public function hasInvoicesForDate($date)
    {
        return $this->invoices()->forDate($date)->count() > 0;
    }

    protected static function acceptDate($date, $fromDate = null)
    {
        return $date->copy()->addDays(-3) >= ($fromDate ?: date('Y-m-d'));
    }

    public function duplicateApprovedBudgets()
    {
        return static::approvedForYear($this->union_id, $this->fiscal_year_start)->where('id', '<>', $this->id);
    }

    public static function approvedForYear($unionId, $fiscalYear)
    {
        return Budget::where('union_id', $unionId)
            ->where('fiscal_year_start', $fiscalYear)
            ->where('status', static::STATUS_APPROVED);
    }

    public function getPreviousBudget()
    {
        return static::approvedForYear($this->union_id, $this->fiscal_year_start->addYears(-1))->first();
    }

    public function getAmount($unit = null, $fund = null, $month = null, $revenuesOnly = false, $nonSpecialFundsOnly = false)
    {
        $budgetDetails = $revenuesOnly ?
                            $this->budgetDetails()->whereHas('account', fn($q) => $q->revenue()) :
                            $this->budgetDetails();

        if ($fund) {

            if ($nonSpecialFundsOnly && !$fund->isDefaultFund()) {
                return 0;
            }

            $budgetDetails = $budgetDetails->where('fund_id', $fund->id);

            if ($unit) {
                $unitPct = $fund->getPctPerUnit($unit);
                $amount = $budgetDetails->leftJoin('budget_detail_quotes AS bdq', 'bdq.budget_detail_id', '=', 'budget_details.id')
                    ->selectRaw('SUM(IFNULL(bdq.calc_pct, '.$unitPct.') * amount) as amount')
                    ->where(fn($q) => $q->whereNull('bdq.customer_id')->orWhere('bdq.customer_id', $unit->id))
                    ->value('amount');
            } else {
                $amount = $budgetDetails->sum('amount');
            }

            $monthPct = $month ? $fund->getPctPerMonth($month) : 1;

            return $amount * $monthPct;

        }else{

            $budgetDetails = !$nonSpecialFundsOnly ? $budgetDetails : $budgetDetails->whereHas('fund', fn($q) => $q->isDefaultFunds());

            $distinctFunds = Fund::whereIn('id', $budgetDetails->pluck('fund_id'))->with('fundQuotes')->get();

            return $distinctFunds->map(function($fund) use ($unit, $month, $revenuesOnly) {

                return $this->getAmount($unit, $fund, $month, $revenuesOnly);

            })->sum();
        }
    }

    public function getRevenue($unit = null, $fund = null, $month = null, $nonSpecialFundsOnly = false)
    {
        return $this->getAmount($unit, $fund, $month, true, $nonSpecialFundsOnly);
    }

    public function getLastMonthNonSpecialRevenue($unit)
    {
        return $this->getRevenue($unit, null, 12, true);
    }

    public function getInvoicesQuery($unit = null, $date = null)
    {
        $invoices = $this->invoices()->with('invoiceDetails')->orderBy('invoiced_at');

        if ($unit) {
            $invoices = $invoices->forUnit($unit->id);
        }

        if ($date) {
            $invoices = $invoices->forDate($date);
        }

        return $invoices;
    }

    public function getRealRevenue($unit = null, $date = null)
    {
        return $this->getInvoicesQuery($unit, $date)->get()->sum(fn($invoice) => $invoice->total_amount);
    }

    /* ATTRIBUTES */
    public function getPeriodLabelAttribute()
    {
        return $this->fiscal_year_start->format('Y-m-d').
            ' '.__('to').' '.
            $this->fiscal_year_start->copy()->addYears(1)->addDays(-1)->format('Y-m-d');
    }

    public function getStatusLabelAttribute(): string
    {
        return static::statuses()[$this->status] ?? '';
    }

    /* ACTIONS */
    public function createRemainingContributions($fromDate = null)
    {
        \DB::transaction(

            fn() => $this->getRemainingDates($fromDate)->each(

                fn($date, $month) => $this->union->units()->with('union')->get()->each(

                    fn($unit) => $this->createContributionInvoiceWithDetails($unit, $date, $month)

                )
            )

        );
    }

    public function createMissedContributions($fromDate = null)
    {
        $lastYearBudget = $this->getPreviousBudget();

        \DB::transaction(

            fn() => $this->getMissingAndEmptyDates($fromDate)->each(

                fn($date, $month) => $this->union->units()->with('union')->get()->each(

                    fn($unit) => $lastYearBudget ?

                        static::createMissedContributionInvoiceWithDetails($unit, $date, $month, $lastYearBudget, $this->id) :

                        $this->createContributionInvoiceWithDetails($unit, $date, $month)

                )
            )

        );
    }

    public function createContributionInvoiceWithDetails($unit, $invoiceDate, $month)
    {
        $invoice = Invoice::createContributionInvoice($unit, $invoiceDate, $this->id);

        $unit->union->getFunds()->each(

            fn($fund) => $invoice->createContributionInvoiceDetail(
                $fund,
                $this->getRevenue($unit, $fund, $month)
            )

        );

        return $invoice;
    }

    public static function createMissedContributionInvoiceWithDetails($unit, $invoiceDate, $month, $lastYearBudget, $forBudgetId = null)
    {
        $invoice = Invoice::createContributionInvoice($unit, $invoiceDate, $forBudgetId ?: $lastYearBudget->id);

        $unit->union->getFunds()->each(

            fn($fund) => $invoice->createContributionInvoiceDetail(
                $fund,
                $lastYearBudget->getRevenue($unit, $fund, 12, true)
            )

        );

        return $invoice;
    }

    public function attemptMarkingApproved($fromDate = null, $adjustments = null, $billDate = null)
    {
        if ($this->duplicateApprovedBudgets()->count()) {
            return false;
        }

        $this->createMissedContributions($fromDate);

        if ($adjustments) {
            collect($adjustments)->each(function($data, $key) use ($billDate) {
                $unit = Unit::with('union.funds')->findOrFail($data['customer_id']);
                $invoice = Invoice::createContributionInvoice($unit, $billDate, $this->id);

                $unitBudget = $this->getRevenue($unit);

                $unit->union->getFunds()->each(

                    fn($fund) => $invoice->createContributionInvoiceDetail(
                        $fund,
                        $unitBudget == 0 ? 0 : ($this->getRevenue($unit, $fund)/$unitBudget * $data['adjustment'])
                    )

                );
            });
        }

        $this->createRemainingContributions($fromDate);

        $this->justMarkApproved();

        return true;
    }

    public function justMarkApproved()
    {
        $this->markBudgetDetailsIncluded();

        $this->markApproved();
    }

    public function regenerateContributionsAfter($fromDate = null)
    {
        \DB::transaction(

            fn() => $this->invoices()->where('invoiced_at', '>=', $fromDate)->get()->each->delete()

        );

        $this->createRemainingContributions($fromDate);

        $this->markBudgetDetailsIncluded();

        return true;
    }

    public static function checkCompletedBudgetForUnion($union)
    {
        //No approved budget this year
        if (!static::approvedForYear($union->id, $union->currentFiscalYearStart()->format('Y-m-d'))->count()) {
            //But there is one last year
            if ($lastYearBudget = static::approvedForYear($union->id, $union->currentFiscalYearStart()->addYears(-1)->format('Y-m-d'))->first()) {

                \DB::transaction(

                    fn() => static::createContributionDates($union->currentFiscalYearStart())->each(

                        fn($date, $month) => (carbon($date)->format('Y-m-d') == now()->format('Y-m-d')) ?

                            $union->units()->with('union')->get()->each(

                                fn($unit) => static::createMissedContributionInvoiceWithDetails($unit, $date, $month, $lastYearBudget)

                            ) :

                            null
                    )

                );
            }
        }
    }

    public function markBudgetDetailsIncluded()
    {
        $this->budgetDetails()->update(['included_at' => now()]);
    }

    public function resetBudgetDetailsIncluded()
    {
        $this->budgetDetails()->update(['included_at' => null]);
    }

    public function markApproved()
    {
        $this->status = static::STATUS_APPROVED;
        $this->save();
    }

    public function markDraft()
    {
        $this->status = static::STATUS_DRAFT;
        $this->save();
    }

    public function markCompleted()
    {
        $this->status = static::STATUS_COMPLETED;
        $this->save();
    }

    public function delete()
    {
        \DB::transaction(function(){

            $this->budgetDetails->each->delete();

        });

        $this->resetInvoices();

        $this->deleteNotifications();

        parent::delete();
    }

    public function resetInvoices()
    {
        \DB::transaction(function(){

            $this->invoices()->get()->each->delete();

        });

        $this->resetBudgetDetailsIncluded();

        $this->markDraft();
    }
}
