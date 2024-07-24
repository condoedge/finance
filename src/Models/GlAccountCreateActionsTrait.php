<?php 

namespace Condoedge\Finance\Models;

use App\Models\Finance\Rgcq;
use App\Models\Finance\GlAccount;
use App\Models\Finance\Tax;

trait GlAccountCreateActionsTrait
{
    /* RELATIONS */

    /* ACTIONS */
    public static function createInitialAccountsIfNone($team)
    {
        if ($team->glAccounts()->count()) {
            return;
        }

        Rgcq::get()->each(
            fn($rgcq) => GlAccount::createGlAccount($team, [
                'group' => $rgcq->group,
                'type' => $rgcq->getTranslations('type'),
                'name' => $rgcq->getTranslations('name'),
                'subname' => $rgcq->getTranslations('subname'),
                'description' => $rgcq->getTranslations('description'),
                'code' => $rgcq->code,
            ])
        );

        //Create tax accounts
        Tax::getOrCreateTaxes($team->id)->each(fn($tax) => static::createTaxAccount($tax, $team));
    }

    public static function createTaxAccount($tax, $team)
    {
        $lastSibling = GlAccount::getLastSibling(Tax::ACCOUNT_CODE, $team->id);

        if ($lastSibling) {
            $nextCode = GlAccount::getNextCode($lastSibling);
        } else {
            $nextCode = Tax::ACCOUNT_CODE + 1;
        }

        GlAccount::createGlAccount($team, [
            'group' => GlAccount::GROUP_EXPENSE,
            'type' => translationsArr('finance-sales-tax'),
            'name' => $tax->getTranslations('name'),
            'subname' => null,
            'code' => $nextCode,
            'tax_id' => $tax->id,
        ]);
    }

    public static function createGlAccount($team, $specs)
    {
        GlAccount::forceCreate(
            array_merge([
                'team_id' => $team->id,
            ],
                $specs
            )
        );
    }

    /* SCOPES */

}