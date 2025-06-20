<?php

namespace Condoedge\Finance\Models\Dto;

use Condoedge\Finance\Casts\SafeDecimal;

class CreateGlTransactionDto extends AbstractDto
{
    public string $fiscal_date;
    public ?string $transaction_description;
    public array $lines;
    
    public function __construct(array $data)
    {
        $this->fiscal_date = $data['fiscal_date'];
        $this->transaction_description = $data['transaction_description'] ?? null;
        $this->lines = $data['lines'] ?? [];
        
        // Validate lines
        foreach ($this->lines as &$line) {
            $line = new CreateGlTransactionLineDto($line);
        }
    }
    
    public function getTotalDebits(): SafeDecimal
    {
        $total = 0;
        foreach ($this->lines as $line) {
            $total += $line->debit_amount;
        }
        return new SafeDecimal($total);
    }
    
    public function getTotalCredits(): SafeDecimal
    {
        $total = 0;
        foreach ($this->lines as $line) {
            $total += $line->credit_amount;
        }
        return new SafeDecimal($total);
    }
    
    public function isBalanced(): bool
    {
        $debits = $this->getTotalDebits();
        $credits = $this->getTotalCredits();
        return $debits->equals($credits);
    }
}

class CreateGlTransactionLineDto extends AbstractDto
{
    public string $account_id;
    public ?string $line_description;
    public float $debit_amount;
    public float $credit_amount;
    
    public function __construct(array $data)
    {
        $this->account_id = $data['account_id'];
        $this->line_description = $data['line_description'] ?? null;
        $this->debit_amount = (float) ($data['debit_amount'] ?? 0);
        $this->credit_amount = (float) ($data['credit_amount'] ?? 0);
    }
    
    public function hasDebit(): bool
    {
        return $this->debit_amount > 0;
    }
    
    public function hasCredit(): bool
    {
        return $this->credit_amount > 0;
    }
    
    public function isValid(): bool
    {
        // Must have either debit or credit, not both, not neither
        return ($this->hasDebit() && !$this->hasCredit()) || 
               (!$this->hasDebit() && $this->hasCredit());
    }
}
