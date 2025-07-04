<?php

namespace Condoedge\Finance\Models;

use Condoedge\Utils\Models\Model;

class FiscalYearSetup extends Model
{
    use \Condoedge\Utils\Models\Traits\BelongsToTeamTrait;

    protected $table = 'fin_fiscal_year_setup';

    protected $fillable = [
        'team_id',
        'fiscal_start_date',
    ];

    protected $casts = [
        'fiscal_start_date' => 'date',
    ];

    /**
     * Get the current active fiscal year setup for a team
     */
    public static function getActiveForTeam(int $teamId = null): ?self
    {
        $teamId = $teamId ?? currentTeamId();

        return static::forTeam($teamId)->first();
    }

    /**
     * Scope for team
     */
    public function scopeForTeam($query, $teamId = null)
    {
        $teamId = $teamId ?? currentTeamId();
        return $query->where('team_id', $teamId);
    }

    /**
     * Get fiscal year for a given date
     */
    public function getFiscalYear(\Carbon\Carbon $date): int
    {
        $fiscalStart = $this->fiscal_start_date->copy();

        // If date is before fiscal start in the same year, use previous fiscal year
        if ($date->lt($fiscalStart->copy()->year($date->year))) {
            return $date->year - 1;
        }

        return $date->year;
    }

    /**
     * Get fiscal year start date for a given year
     */
    public function getFiscalYearStart(int $fiscalYear): \Carbon\Carbon
    {
        return $this->fiscal_start_date->copy()->year($fiscalYear);
    }

    /**
     * Get fiscal year end date for a given year
     */
    public function getFiscalYearEnd(int $fiscalYear): \Carbon\Carbon
    {
        return $this->getFiscalYearStart($fiscalYear + 1)->subDay();
    }

    /**
     * Check if a date falls within a fiscal year
     */
    public function isDateInFiscalYear(\Carbon\Carbon $date, int $fiscalYear): bool
    {
        $start = $this->getFiscalYearStart($fiscalYear);
        $end = $this->getFiscalYearEnd($fiscalYear);

        return $date->between($start, $end);
    }

    /**
     * Get the active fiscal year setup
     */
    public static function getActive(): ?self
    {
        return static::first();
    }

    /**
     * Get fiscal year from date
     */
    public static function getFiscalYearFromDate(\Carbon\Carbon $date): ?int
    {
        $setup = static::first();
        if (!$setup) {
            return null;
        }

        $fiscalStartMonth = $setup->fiscal_start_date->month;
        $fiscalStartDay = $setup->fiscal_start_date->day;

        // If date is after fiscal start in same year, it's next fiscal year
        if ($date->month > $fiscalStartMonth ||
            ($date->month == $fiscalStartMonth && $date->day >= $fiscalStartDay)) {
            return $date->year + 1;
        }

        return $date->year;
    }
}
