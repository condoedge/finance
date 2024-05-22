<?php

namespace Condoedge\Finance\Models;

use App\Models\Condo\Unit;
use App\Models\Model;
use App\Models\Crm\BelongsToUnion;
use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bank extends Model
{
    use SoftDeletes,
        BelongsToUnion,
        BelongsToUser;

    /* RELATIONSHIPS */
    public function account()
    {
    	return $this->hasOne(GlAccount::class);
    }    

    public function units()
    {
        return $this->belongsToMany(Unit::class);
    }

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
    public function delete()
    {
        $this->units()->sync([]); //detaches

        if ($account = $this->account) {
            $account->bank_id = null;
            $account->save();
        }

        //TODO: delete related account?

        parent::delete();
    }
}
