<?php

namespace Condoedge\Finance\Models\Traits;

use Condoedge\Finance\Services\FiscalYearService;
use Carbon\Carbon;
use Condoedge\Finance\Enums\GlTransactionTypeEnum;
use Illuminate\Http\Client\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Fiscal Period Validation Trait
 * 
 * Validates that transactions are only posted to open fiscal periods
 * Include this trait in transaction models to enforce period validation
 */
trait ValidatesFiscalPeriod
{
    /**
     * Boot the trait
     */
    protected static function bootValidatesFiscalPeriod(): void
    {
        static::creating(function ($model) {
            $model->validateFiscalPeriod();
        });
        
        static::updating(function ($model) {
            // Only validate if fiscal_date is being changed
            if ($model->isDirty('fiscal_date')) {
                $model->validateFiscalPeriod();
            }
        });
    }
    
    /**
     * Validate that the transaction can be posted to the fiscal period
     */
    protected function validateFiscalPeriod(): void
    {
        if (!$this->shouldValidateFiscalPeriod()) {
            return;
        }
        
        $fiscalDate = $this->getFiscalDateForValidation();
        $module = $this->getModuleForValidation();
        $teamId = $this->getTeamIdForValidation();
        
        if (!$fiscalDate || !$module || !$teamId) {
            return; // Skip validation if required data is missing
        }
        
        $fiscalService = app(FiscalYearService::class);
        
        try {
            $fiscalService->validateTransactionDate($fiscalDate, $module, $teamId);
        } catch (ValidationException $e) {
            throw new HttpException(403, __('finance-fiscal-period-is-closed-for-this-transaction'), $e);
        }
    }
    
    /**
     * Determine if fiscal period validation should be performed
     * Override this method to customize validation logic
     */
    protected function shouldValidateFiscalPeriod(): bool
    {
        // Skip validation if explicitly disabled
        if (property_exists($this, 'skipFiscalPeriodValidation') && $this->skipFiscalPeriodValidation) {
            return false;
        }
        
        // Skip validation for certain transaction types
        if (method_exists($this, 'isSystemGenerated') && $this->isSystemGenerated()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get the fiscal date for validation
     * Override this method to specify which date field to use
     */
    protected function getFiscalDateForValidation(): ?Carbon
    {
        // Try common date field names
        $dateFields = ['fiscal_date', 'transaction_date', 'posting_date', 'date'];
        
        foreach ($dateFields as $field) {
            if (isset($this->attributes[$field])) {
                return Carbon::parse($this->attributes[$field]);
            }
        }
        
        return null;
    }
    
    /**
     * Get the module for validation
     * Override this method to specify the module
     */
    protected function getModuleForValidation(): ?GlTransactionTypeEnum
    {
        // If model has a module property
        if (property_exists($this, 'fiscalModule') && $this->fiscalModule) {
            return $this->fiscalModule;
        }
        
        // Try to determine from transaction type
        if (isset($this->attributes['transaction_type'])) {
            return $this->mapTransactionTypeToModule($this->attributes['transaction_type']);
        }
        
        // Default to GL for manual entries
        return GlTransactionTypeEnum::MANUAL_GL;
    }
    
    /**
     * Get the team ID for validation
     * Override this method to specify team ID logic
     */
    protected function getTeamIdForValidation(): ?int
    {
        // If model uses BelongsToTeamTrait
        if (isset($this->attributes['team_id'])) {
            return $this->attributes['team_id'];
        }
        
        // Try to get from current team context
        if (function_exists('currentTeamId')) {
            return currentTeamId();
        }
        
        return null;
    }
    
    /**
     * Map transaction type to module
     */
    protected function mapTransactionTypeToModule(int $transactionType): GlTransactionTypeEnum
    {
        try {
            return GlTransactionTypeEnum::from($transactionType);
        } catch (\ValueError $e) {
            // If transaction type is invalid, default to GL
            return GlTransactionTypeEnum::MANUAL_GL;
        }
    }
    
    /**
     * Check if the current fiscal period is open for this transaction
     */
    public function isFiscalPeriodOpen(): bool
    {
        try {
            $this->validateFiscalPeriod();
            return true;
        } catch (ValidationException $e) {
            return false;
        }
    }
}
