<?php

namespace Condoedge\Finance\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Condoedge\Finance\Models\GlTransactionHeader createTransaction(\Condoedge\Finance\Models\Dto\Gl\CreateGlTransactionDto $dto)
 * @method static \Condoedge\Finance\Models\GlTransactionHeader postTransaction(string|\Condoedge\Finance\Models\GlTransactionHeader $transaction)
 * @method static \Condoedge\Finance\Models\GlTransactionHeader reverseTransaction(int $transactionId, ?string $reversalDescription = null)
 * @method static \Condoedge\Finance\Casts\SafeDecimal getAccountBalance(int $accountId, ?\Carbon\Carbon $startDate = null, ?\Carbon\Carbon $endDate = null, bool $postedOnly = true)
 * @method static array getTrialBalance(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate, bool $postedOnly = true)
 * @method static void closeFiscalPeriod(string $periodId)
 * @method static void openFiscalPeriod(string $periodId)
 * @method static void validateAccountAbleToTransaction($accountId, $transactionType)
 * @method static void validateNaturalAccountAbleToTransaction($naturalAccountId, $transactionType)
 *
 * @see \Condoedge\Finance\Services\GlTransactionService
 */
class GlTransactionService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Condoedge\Finance\Services\GlTransactionServiceInterface::class;
    }
}
