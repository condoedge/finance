<?php

namespace Condoedge\Finance\Models;

use Kompo\Auth\Facades\TeamModel;
use Kompo\Database\HasTranslations;

class ExpenseReportType extends AbstractMainFinanceModel
{
    use HasTranslations;
    
    protected $table = 'fin_expense_report_types';

    protected $translatable = ['name'];

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'expense_type_id');
    }

    public function team()
    {
        return $this->belongsTo(TeamModel::class, 'team_id');
    }

    // SCOPES
    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }
}