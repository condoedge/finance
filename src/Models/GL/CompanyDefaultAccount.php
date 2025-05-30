<?php

namespace Condoedge\Finance\Models\GL;

use Condoedge\Finance\Models\AbstractMainFinanceModel;

class CompanyDefaultAccount extends AbstractMainFinanceModel
{
    protected $table = 'fin_company_default_accounts';
    protected $primaryKey = 'setting_name';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'setting_name',
        'account_id',
        'description',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    // Constants for default account types
    const DEFAULT_REVENUE_ACCOUNT = 'DefaultRevenueAccount';
    const DEFAULT_EXPENSE_ACCOUNT = 'DefaultExpenseAccount';
    const DEFAULT_BANK_ACCOUNT = 'DefaultBankAccount';
    const DEFAULT_ACCOUNTS_PAYABLE = 'DefaultAccountsPayable';
    const DEFAULT_ACCOUNTS_RECEIVABLE = 'DefaultAccountsReceivable';
    const DEFAULT_COGS_ACCOUNT = 'DefaultCOGSAccount';
    const DEFAULT_DISCOUNT_ACCOUNT = 'DefaultDiscountAccount';
    const DEFAULT_TAX_PAYABLE_ACCOUNT = 'DefaultTaxPayableAccount';
    const DEFAULT_RETAINED_EARNINGS = 'DefaultRetainedEarnings';

    /**
     * Get default account by type
     */
    public static function getDefaultAccount(string $settingName): ?string
    {
        $setting = static::where('setting_name', $settingName)
                        ->where('is_active', true)
                        ->first();

        return $setting ? $setting->account_id : null;
    }

    /**
     * Set default account
     */
    public static function setDefaultAccount(string $settingName, string $accountId, string $description = null): bool
    {
        return static::updateOrCreate(
            ['setting_name' => $settingName],
            [
                'account_id' => $accountId,
                'description' => $description,
                'is_active' => true
            ]
        );
    }

    /**
     * Get all active default accounts
     */
    public static function getActiveDefaults()
    {
        return static::where('is_active', true)->get();
    }

    /**
     * Get default revenue account
     */
    public static function getDefaultRevenueAccount(): ?string
    {
        return static::getDefaultAccount(self::DEFAULT_REVENUE_ACCOUNT);
    }

    /**
     * Get default expense account
     */
    public static function getDefaultExpenseAccount(): ?string
    {
        return static::getDefaultAccount(self::DEFAULT_EXPENSE_ACCOUNT);
    }

    /**
     * Get default bank account
     */
    public static function getDefaultBankAccount(): ?string
    {
        return static::getDefaultAccount(self::DEFAULT_BANK_ACCOUNT);
    }

    /**
     * Get default accounts payable account
     */
    public static function getDefaultAccountsPayable(): ?string
    {
        return static::getDefaultAccount(self::DEFAULT_ACCOUNTS_PAYABLE);
    }

    /**
     * Get default accounts receivable account
     */
    public static function getDefaultAccountsReceivable(): ?string
    {
        return static::getDefaultAccount(self::DEFAULT_ACCOUNTS_RECEIVABLE);
    }

    /**
     * Validate that account exists before setting
     */
    public function setAccountIdAttribute($value)
    {
        // You can add validation here to ensure the account exists
        // $account = GlAccount::find($value);
        // if (!$account) {
        //     throw new \Exception("Account {$value} does not exist");
        // }
        
        $this->attributes['account_id'] = $value;
    }
}
