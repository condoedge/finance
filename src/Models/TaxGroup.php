<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Facades\TaxService;
use Condoedge\Utils\Models\Model;

class TaxGroup extends Model
{
    use \Condoedge\Utils\Models\Traits\BelongsToTeamTrait;

    protected $table = 'fin_taxes_groups';

    protected $fillable = [
        'name',
        'team_id',
    ];

    /* RELATIONSHIPS */
    public function taxes()
    {
        return $this->belongsToMany(Tax::class, 'fin_taxes_group_taxes', 'tax_group_id', 'tax_id');
    }

    /* ACTIONS */
    /**
     * Create tax group with taxes
     */
    public static function createWithTaxes(string $name, \Illuminate\Support\Collection $taxIds): self
    {
        return TaxService::createTaxGroup($name, $taxIds);
    }

    /**
     * Update taxes in this group
     */
    public function updateTaxes(\Illuminate\Support\Collection $taxIds): self
    {
        return TaxService::updateTaxGroupTaxes($this, $taxIds);
    }
}
