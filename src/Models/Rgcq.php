<?php

namespace Condoedge\Finance\Models;

use Condoedge\Utils\Models\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kompo\Database\HasTranslations;

class Rgcq extends Model
{
    use SoftDeletes,
        HasTranslations;

    protected $translatable = [
        'type',
        'name',
        'subname',
        'description',
    ];

    /* RELATIONSHIPS */

    /* ATTRIBUTES */

    /* CALCULATED FIELDS */

    /* SCOPES */
}
