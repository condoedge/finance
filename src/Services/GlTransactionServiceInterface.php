<?php

namespace Condoedge\Finance\Services;

use Carbon\Carbon;
use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Models\Dto\Gl\CreateGlTransactionDto;
use Condoedge\Finance\Models\GlTransactionHeader;

interface GlTransactionServiceInterface
{
    /**
     * Create a complete GL transaction with lines
     *
     * @param array $headerData
     * @param array $lines
     *
     * @return GlTransactionHeader
     *
     * @throws \Exception
     */
    public function createTransaction(CreateGlTransactionDto $dto): GlTransactionHeader;
    /**
     * Post a transaction (make it final)
     *
     * @param string|GlTransactionHeader $transaction
     *
     * @return GlTransactionHeader
     *
     * @throws \Exception
     */
    public function postTransaction($transaction): GlTransactionHeader;

    /**
     * Reverse a posted transaction
     *
     * @param int $transactionId
     * @param string|null $reversalDescription
     *
     * @return GlTransactionHeader
     *
     * @throws \Exception
     */
    public function reverseTransaction(int $transactionId, string $reversalDescription = null): GlTransactionHeader;

    /**
     * Get account balance for a date range
     *
     * @param string $accountId
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @param bool $postedOnly
     *
     * @return SafeDecimal
     */
    public function getAccountBalance(
        int $accountId,
        Carbon $startDate = null,
        Carbon $endDate = null,
        bool $postedOnly = true
    ): SafeDecimal;

    /**
     * Get trial balance for a period
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param bool $postedOnly
     *
     * @return array
     */
    public function getTrialBalance(
        Carbon $startDate,
        Carbon $endDate,
        bool $postedOnly = true
    ): array;

    /**
     * Close fiscal period for GL
     *
     * @param string $periodId
     *
     * @throws \Exception
     */
    public function closeFiscalPeriod(string $periodId): void;

    /**
     * Open fiscal period for GL
     *
     * @param string $periodId
     */
    public function openFiscalPeriod(string $periodId): void;

    public function validateAccountAbleToTransaction($accountId, $transactionType);
    public function validateNaturalAccountAbleToTransaction($naturalAccountId, $transactionType);
}
