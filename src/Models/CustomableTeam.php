<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Models\Traits\CustomableTeamTrait;
use Condoedge\Utils\Models\Model;

// Not used for queries, just a service model to create customers from teams
class CustomableTeam extends Model implements CustomableContract
{
    use CustomableTeamTrait;
}
