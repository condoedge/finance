<?php

namespace Condoedge\Finance\Models;

use Condoedge\Utils\Models\Model;

/**
 * Company Default Accounts Model
 * Stores default GL accounts for various system functions at company/team level
 * 
 * @property int $id
 * @property int $team_id
 * @property string $setting_name e.g., 'DefaultRevenueAccount', 'DefaultExpenseAccount'
 * @property string $account_id Reference to fin_gl_accounts
 * @property string $description
 */
class CompanyDefaultAccount extends Model
{
    use \Condoedge\Utils\Models\Traits\BelongsToTeamTrait;
    
    protected $table = 'fin_company_default_accounts';
    
    protected $fillable = [
        'team_id',
        'setting_name',
        'account_id',
        'description',
    ];
    
    // Default account setting names
    const DEFAULT_REVENUE_ACCOUNT = 'DefaultRevenueAccount';
    const DEFAULT_EXPENSE_ACCOUNT = 'DefaultExpenseAccount';
    const DEFAULT_BANK_ACCOUNT = 'DefaultBankAccount';
    const DEFAULT_ACCOUNTS_RECEIVABLE = 'DefaultAccountsReceivable';
    const DEFAULT_ACCOUNTS_PAYABLE = 'DefaultAccountsPayable';
    const DEFAULT_COGS_ACCOUNT = 'DefaultCOGSAccount';
    
    /**
     * Get account relationship
     */
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id', 'account_id');
    }
    
    /**
     * Get default account for a specific setting
     */
    public static function getDefaultAccount($settingName, $teamId = null)
    {
        $teamId = $teamId ?? currentTeamId();
        
        $defaultAccount = static::where('team_id', $teamId)
            ->where('setting_name', $settingName)
            ->first();
        
        return $defaultAccount ? $defaultAccount->account : null;
    }
    
    /**
     * Set default account for a setting
     */
    public static function setDefaultAccount($settingName, $accountId, $teamId = null, $description = null)
    {
        $teamId = $teamId ?? currentTeamId();
        
        return static::updateOrCreate(
            [
                'team_id' => $teamId,
                'setting_name' => $settingName,
            ],
            [
                'account_id' => $accountId,
                'description' => $description,
            ]
        );
    }
    
    /**
     * Get all default accounts for a team
     */
    public static function getAllForTeam($teamId = null)
    {
        $teamId = $teamId ?? currentTeamId();
        
        return static::where('team_id', $teamId)
            ->with('account')
            ->get()
            ->pluck('account', 'setting_name');
    }
    
    /**
     * Get available setting names
     */
    public static function getAvailableSettings()
    {
        return [
            static::DEFAULT_REVENUE_ACCOUNT => 'Default Revenue Account',
            static::DEFAULT_EXPENSE_ACCOUNT => 'Default Expense Account',
            static::DEFAULT_BANK_ACCOUNT => 'Default Bank Account',
            static::DEFAULT_ACCOUNTS_RECEIVABLE => 'Default Accounts Receivable',
            static::DEFAULT_ACCOUNTS_PAYABLE => 'Default Accounts Payable',
            static::DEFAULT_COGS_ACCOUNT => 'Default Cost of Goods Sold',
        ];
    }
    
    /**
     * Scope for team
     */
    public function scopeForTeam($query, $teamId = null)
    {
        $teamId = $teamId ?? currentTeamId();
        return $query->where('team_id', $teamId);
    }
}
