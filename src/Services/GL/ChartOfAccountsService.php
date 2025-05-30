<?php

namespace Condoedge\Finance\Services\GL;

use Condoedge\Finance\Models\GL\GlAccount;
use Condoedge\Finance\Models\GL\AccountSegmentDefinition;
use Condoedge\Finance\Models\GL\GlSegmentValue;

class ChartOfAccountsService
{
    /**
     * Setup account structure
     */
    public function setupAccountStructure(array $segments): bool
    {
        foreach ($segments as $position => $segment) {
            AccountSegmentDefinition::updateOrCreate(
                ['segment_position' => $position + 1],
                [
                    'segment_length' => $segment['length'],
                    'segment_name' => $segment['name'],
                    'segment_description' => $segment['description'] ?? null,
                    'is_active' => true
                ]
            );

            // Create structure definition in segment values
            GlSegmentValue::updateOrCreate(
                [
                    'segment_type' => GlSegmentValue::TYPE_STRUCTURE_DEFINITION,
                    'segment_number' => $position + 1
                ],
                [
                    'segment_value' => null,
                    'segment_description' => $segment['name'],
                    'is_active' => true
                ]
            );
        }

        return true;
    }

    /**
     * Create segment value
     */
    public function createSegmentValue(int $segmentNumber, string $value, string $description): GlSegmentValue
    {
        // Validate segment structure exists
        $definition = AccountSegmentDefinition::getByPosition($segmentNumber);
        if (!$definition) {
            throw new \Exception("Segment definition for position {$segmentNumber} not found");
        }

        // Validate value length
        if (strlen($value) !== $definition->segment_length) {
            throw new \Exception("Segment value must be {$definition->segment_length} characters long");
        }

        return GlSegmentValue::createSegmentValue($segmentNumber, $value, $description);
    }

    /**
     * Create GL account
     */
    public function createGlAccount(
        array $segments,
        string $description,
        string $accountType = null,
        string $accountCategory = null,
        bool $allowManualEntry = true
    ): GlAccount {
        // Validate segments exist
        foreach ($segments as $position => $value) {
            if (!GlSegmentValue::isValidSegmentValue($position + 1, $value)) {
                throw new \Exception("Invalid segment value '{$value}' for position " . ($position + 1));
            }
        }

        return GlAccount::createFromSegments($segments, $description, $accountType);
    }

    /**
     * Get chart of accounts with hierarchy
     */
    public function getChartOfAccounts(array $filters = []): array
    {
        $query = GlAccount::query()->active();

        // Apply filters
        if (isset($filters['account_type'])) {
            $query->where('account_type', $filters['account_type']);
        }

        if (isset($filters['segment1'])) {
            $query->where('segment1_value', $filters['segment1']);
        }

        if (isset($filters['segment2'])) {
            $query->where('segment2_value', $filters['segment2']);
        }

        if (isset($filters['segment3'])) {
            $query->where('segment3_value', $filters['segment3']);
        }

        $accounts = $query->orderBy('account_id')->get();

        return $this->buildAccountHierarchy($accounts);
    }

    /**
     * Build account hierarchy for reporting
     */
    protected function buildAccountHierarchy($accounts): array
    {
        $hierarchy = [];
        
        foreach ($accounts as $account) {
            $segments = [];
            for ($i = 1; $i <= 5; $i++) {
                $segmentValue = $account->{"segment{$i}_value"};
                if ($segmentValue) {
                    $segments[] = [
                        'position' => $i,
                        'value' => $segmentValue,
                        'description' => GlSegmentValue::getSegmentDescription($i, $segmentValue)
                    ];
                }
            }

            $hierarchy[] = [
                'account_id' => $account->account_id,
                'description' => $account->account_description,
                'type' => $account->account_type,
                'category' => $account->account_category,
                'segments' => $segments,
                'balance' => $account->getBalance(),
                'is_active' => $account->is_active,
                'allow_manual_entry' => $account->allow_manual_entry
            ];
        }

        return $hierarchy;
    }

    /**
     * Validate account structure setup
     */
    public function validateAccountStructure(): array
    {
        $errors = [];
        
        $definitions = AccountSegmentDefinition::getActiveDefinitions();
        
        if ($definitions->isEmpty()) {
            $errors[] = 'No account segment definitions found. Please setup account structure first.';
            return $errors;
        }

        // Check for gaps in segment positions
        $positions = $definitions->pluck('segment_position')->toArray();
        sort($positions);
        
        for ($i = 1; $i <= max($positions); $i++) {
            if (!in_array($i, $positions)) {
                $errors[] = "Missing segment definition for position {$i}";
            }
        }

        return $errors;
    }

    /**
     * Get accounts for dropdown/selection
     */
    public function getAccountsForSelection(bool $manualEntryOnly = false): array
    {
        $query = GlAccount::query()->active();
        
        if ($manualEntryOnly) {
            $query->manualEntryAllowed();
        }

        return $query->orderBy('account_id')
                    ->get()
                    ->map(function($account) {
                        return [
                            'value' => $account->account_id,
                            'label' => $account->account_id . ' - ' . $account->getFullDescription()
                        ];
                    })
                    ->toArray();
    }

    /**
     * Disable account
     */
    public function disableAccount(string $accountId): bool
    {
        $account = GlAccount::where('account_id', $accountId)->first();
        
        if (!$account) {
            throw new \Exception("Account {$accountId} not found");
        }

        // Check if account has transactions
        if ($account->glEntries()->exists()) {
            // Don't physically disable if has transactions, just mark as inactive
            $account->is_active = false;
            $account->allow_manual_entry = false;
        } else {
            $account->is_active = false;
        }

        return $account->save();
    }

    /**
     * Enable account
     */
    public function enableAccount(string $accountId, bool $allowManualEntry = true): bool
    {
        $account = GlAccount::where('account_id', $accountId)->first();
        
        if (!$account) {
            throw new \Exception("Account {$accountId} not found");
        }

        $account->is_active = true;
        $account->allow_manual_entry = $allowManualEntry;

        return $account->save();
    }

    /**
     * Get account balances for trial balance
     */
    public function getTrialBalance($asOfDate = null): array
    {
        $accounts = GlAccount::active()->get();
        $balances = [];

        foreach ($accounts as $account) {
            $balance = $account->getBalance($asOfDate);
            
            if ($balance != 0) {
                $balances[] = [
                    'account_id' => $account->account_id,
                    'account_description' => $account->account_description,
                    'account_type' => $account->account_type,
                    'debit_balance' => $balance > 0 ? $balance : 0,
                    'credit_balance' => $balance < 0 ? abs($balance) : 0,
                    'segments' => [
                        'segment1' => $account->segment1_value,
                        'segment2' => $account->segment2_value,
                        'segment3' => $account->segment3_value,
                        'segment4' => $account->segment4_value,
                        'segment5' => $account->segment5_value,
                    ]
                ];
            }
        }

        return $balances;
    }

    /**
     * Import chart of accounts from array
     */
    public function importChartOfAccounts(array $accounts): array
    {
        $results = [
            'success' => 0,
            'errors' => []
        ];

        foreach ($accounts as $index => $accountData) {
            try {
                $this->createGlAccount(
                    $accountData['segments'],
                    $accountData['description'],
                    $accountData['account_type'] ?? null,
                    $accountData['account_category'] ?? null,
                    $accountData['allow_manual_entry'] ?? true
                );
                $results['success']++;
            } catch (\Exception $e) {
                $results['errors'][] = "Row {$index}: " . $e->getMessage();
            }
        }

        return $results;
    }
}
