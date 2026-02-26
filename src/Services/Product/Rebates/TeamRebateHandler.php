<?php

namespace Condoedge\Finance\Services\Product\Rebates;

use Condoedge\Finance\Models\Product;
use Condoedge\Finance\Models\Rebate;
use Condoedge\Utils\Facades\TeamModel;

class TeamRebateHandler extends AbstractRebateHandler 
{
    function shouldApplyRebate(Product $product, Rebate $rebate): bool
    {
        $teamId = $this->context['team_id'] ?? null;

        if (!$teamId) {
            return false;
        }

        $rebateParams = $rebate->rebate_logic_parameters;

        return $teamId === $rebateParams['team_id'];
    }

    function getHandlerLabel(): string
    {
        return __('translate.team');
    }

    function getHandlerParamsFields()
    {
        return _Rows(
            _Select('translate.team')->name('rebate_logic_parameters.team_id')
                ->searchOptions(3, 'searchTeams', 'retrieveTeams')->required(),
        );  
    }

    function getHandlerParamsRules(): array
    {
        return [
            'rebate_logic_parameters.team_id' => ['required', 'exists:teams,id'],
        ];
    }

    public function getHandlerParamsLabel($params): string
    {
        return __('translate.team', $params);
    }

    public function searchTeams($search)
    {
        $teamsIds = auth()->user()->getAllAccessibleTeamIds($search, 30);

        return TeamModel::whereIn('id', $teamsIds)
            ->pluck('team_name', 'id');
    }

    public function retrieveTeam($id)
    {
        $team = TeamModel::find($id);

        return [
            $id => $team->team_name,
        ];
    }
}
