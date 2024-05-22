<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Models\BudgetDetailQuote;
use App\Models\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BudgetDetail extends Model
{
    use SoftDeletes;

    public function budget()
    {
    	return $this->belongsTo(Budget::class);
    }

    public function account()
    {
    	return $this->belongsTo(GlAccount::class);
    }

    public function fund()
    {
    	return $this->belongsTo(Fund::class);
    }

    public function budgetDetailQuotes()
    {
        return $this->hasMany(BudgetDetailQuote::class);
    }

    /* CALCULATED FIELDS */

    /* SCOPES */
    public function scopeNotInvoiced($query)
    {
        $query->whereNull('included_at')->where('amount','<>',0);
    }



    /* ACTIONS */
    public function delete()
    {
        $this->budgetDetailQuotes()->delete();
        
        parent::delete();
    }

    public function excludeFromContributions()
    {
        currentUnion()->units->each(function($customer){
            $bdQuote = new BudgetDetailQuote();
            $bdQuote->budget_detail_id = $this->id;
            $bdQuote->customer_id = $customer->id;
            $bdQuote->fractions = 0;
            $bdQuote->calc_pct = 0;
            $bdQuote->save();
        });
        $this->excluded = 1;
        $this->save();
    }

    public function includeInContributions()
    {
        $this->budgetDetailQuotes()->forceDelete();
        $this->excluded = 0;
        $this->save();
    }
    
}
