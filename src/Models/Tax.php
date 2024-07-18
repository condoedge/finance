<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Models\GlAccount;
use Kompo\Auth\Models\Model;

class Tax extends Model
{
    use \Kompo\Auth\Models\Teams\BelongsToTeamTrait;

    use \Kompo\Database\HasTranslations;
    protected $translatable = [
        'name',
    ];

    public CONST ACCOUNT_CODE = 2600;

    /* ATTRIBUTES */
    public function getLabelAttribute()
    {
    	return $this->name.' ('.$this->rate_label.')';
    }

    public function getRateLabelAttribute()
    {
    	return ($this->rate * 100).'%';
    }

    /* CALCULATED FIELDS */
    public static function seedData()
    {
        return collect([
            'GST' => 0.05,
            'QST' => 0.09975,
        ]);
    }

    public static function amountWithTaxes($amount, $taxes)
    {
        $totalTaxRate = $taxes ? $taxes->pluck('rate')->sum() : 0;
        
        return round($amount * (1 + $totalTaxRate), 2);
    }

    public function taxAmount($amount)
    {
        return round($this->rate * $amount, 2);
    }

    public static function getDefaultTaxes()
    {
        return Tax::whereIn('id', \Cache::get('charge-latest-taxes-'.auth()->id()) ?: [])->get();
    }

    public static function setDefaultTaxes($taxIds)
    {
        \Cache::forever('charge-latest-taxes-'.auth()->id(), $taxIds);
    }

    /* ACTIONS */

    /* ELEMENTS */
    public static function getTaxesOptions()
    {
        $taxes = Tax::getOrCreateTaxes();

        return $taxes->mapWithKeys(function($tax){
            return [$tax->id => '<span data-tax="'.$tax->rate.'" data-id="'.$tax->id.'">'.$tax->label.'</span>'];
        });
    }

    public static function getOrCreateTaxes($teamId = null)
    {
        $teamId = $teamId ?: currentTeamId();
        $taxes = Tax::forTeam($teamId)->get();

        if (!$taxes->count()) {
            $taxes = Tax::seedData()->map(fn ($rate, $name) => Tax::forceCreate([
                'team_id' => $teamId,
                'name' => translationsArr($name),
                'rate' => $rate
            ]));
        }

        return $taxes;
    }
}
