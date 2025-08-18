<?php

namespace Condoedge\Finance\Models;

use Condoedge\Utils\Models\Model;

class Bank extends Model
{
    use \Kompo\Auth\Models\Traits\BelongsToUserTrait;
    use \Kompo\Auth\Models\Teams\BelongsToTeamTrait;

    /* RELATIONSHIPS */

    /* ATTRIBUTES */
    public function getDisplayAttribute()
    {
        return $this->name.' (***'.substr($this->account_number, -3).')';
    }

    /* CALCULATED FIELDS */
    public static function validationRules()
    {
        return [
            'name' => 'required',
            'institution' => 'required|digits:3',
            'branch' => 'required|digits:5',
            'account_number' => 'required|digits_between:5,12',
        ];
    }

    /* ELEMENTS */
    public static function accountNumberInputs()
    {
        return _Columns(
            _Input('institution')->name('institution')
                ->placeholder('XXX')
                ->col('col-sm-3'),
            _Input('transit')->name('branch')
                ->placeholder('XXXXX')
                ->col('col-sm-3'),
            _Input('finance.account-number')->name('account_number')
                ->placeholder('finance.account-number-placeholder')
                ->col('col-sm-6'),            
        );
    }

    /* ACTIONS */
}
