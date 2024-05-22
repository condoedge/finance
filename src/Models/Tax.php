<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Models\GlAccount;
use App\Models\Model;

class Tax extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;
    use \Kompo\Auth\Models\Teams\BelongsToTeam;

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
    public function createTaxAccount($unionId)
    {
        $lastSibling = GlAccount::getLastSibling(static::ACCOUNT_CODE, $unionId);

        if ($lastSibling) {
            $nextCode = GlAccount::getNextCode($lastSibling);
        } else {
            $nextCode = static::ACCOUNT_CODE + 1;
        }

        GlAccount::forceCreate([
            'union_id' => $unionId,
            'level' => GlAccount::LEVEL_MEDIUM,
            'group' => GlAccount::GROUP_EXPENSE,
            'type' => translationsArr('finance.sales-tax'),
            'name' => $this->getTranslations('name'),
            'subname' => null,
            'code' => $nextCode,
            'tax_id' => $this->id,
        ]);
    }

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
