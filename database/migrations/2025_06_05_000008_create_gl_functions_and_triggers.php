<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateGlFunctionsAndTriggers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Create validate_fiscal_period_open function
        $this->createValidateFiscalPeriodOpenFunction();
        
        // Create validate_gl_transaction_balance function
        $this->createValidateGlTransactionBalanceFunction();
        
        // Create get_next_gl_transaction_number function
        $this->createGetNextGlTransactionNumberFunction();
        
        // Create GL transaction integrity triggers
        $this->createGlTransactionIntegrityTriggers();
        
        // Create GL line integrity triggers
        $this->createGlLineIntegrityTriggers();
    }
    
    protected function createValidateFiscalPeriodOpenFunction()
    {
        DB::unprepared("
            DROP FUNCTION IF EXISTS validate_fiscal_period_open;
            
            CREATE FUNCTION validate_fiscal_period_open(
                p_fiscal_date DATE, 
                p_transaction_type TINYINT
            ) RETURNS BOOLEAN
            READS SQL DATA
            DETERMINISTIC
            BEGIN
                DECLARE period_open BOOLEAN DEFAULT FALSE;
                DECLARE fiscal_period VARCHAR(10);
                
                -- Determine fiscal period from date
                SELECT period_id INTO fiscal_period
                FROM fin_fiscal_periods 
                WHERE p_fiscal_date BETWEEN start_date AND end_date
                LIMIT 1;
                
                -- Check if period is open for the specific module
                SELECT CASE 
                    WHEN p_transaction_type = 1 THEN is_open_gl    -- Manual GL
                    WHEN p_transaction_type = 2 THEN is_open_bnk   -- Bank
                    WHEN p_transaction_type = 3 THEN is_open_rm    -- Receivable
                    WHEN p_transaction_type = 4 THEN is_open_pm    -- Payable
                    ELSE FALSE
                END INTO period_open
                FROM fin_fiscal_periods 
                WHERE period_id = fiscal_period;
                
                RETURN COALESCE(period_open, FALSE);
            END
        ");
    }
    
    protected function createValidateGlTransactionBalanceFunction()
    {
        DB::unprepared("
            DROP FUNCTION IF EXISTS validate_gl_transaction_balance;
            
            CREATE FUNCTION validate_gl_transaction_balance(p_gl_transaction_id VARCHAR(50)) 
            RETURNS BOOLEAN
            READS SQL DATA
            DETERMINISTIC
            BEGIN
                DECLARE total_debits DECIMAL(19,5) DEFAULT 0;
                DECLARE total_credits DECIMAL(19,5) DEFAULT 0;
                DECLARE is_balanced BOOLEAN DEFAULT FALSE;
                
                -- Calculate totals
                SELECT 
                    COALESCE(SUM(debit_amount), 0),
                    COALESCE(SUM(credit_amount), 0)
                INTO total_debits, total_credits
                FROM fin_gl_transaction_lines 
                WHERE gl_transaction_id = p_gl_transaction_id 
                AND deleted_at IS NULL;
                
                -- Check if balanced (allowing for small rounding differences)
                SET is_balanced = ABS(total_debits - total_credits) < 0.00001;
                
                RETURN is_balanced;
            END
        ");
    }
    
    protected function createGetNextGlTransactionNumberFunction()
    {
        DB::unprepared("
            DROP FUNCTION IF EXISTS get_next_gl_transaction_number;
            
            CREATE FUNCTION get_next_gl_transaction_number(p_sequence_type VARCHAR(20), p_fiscal_year SMALLINT) 
            RETURNS BIGINT
            MODIFIES SQL DATA
            DETERMINISTIC
            BEGIN
                DECLARE next_number BIGINT;
                
                -- Get and increment the sequence in a single atomic operation
                UPDATE fin_gl_sequences 
                SET last_number = last_number + 1,
                    updated_at = NOW()
                WHERE sequence_type = p_sequence_type 
                AND (fiscal_year = p_fiscal_year OR (fiscal_year IS NULL AND p_fiscal_year IS NULL));
                
                -- Get the new number
                SELECT last_number INTO next_number
                FROM fin_gl_sequences 
                WHERE sequence_type = p_sequence_type 
                AND (fiscal_year = p_fiscal_year OR (fiscal_year IS NULL AND p_fiscal_year IS NULL));
                
                RETURN next_number;
            END
        ");
    }
    
    protected function createGlTransactionIntegrityTriggers()
    {
        DB::unprepared("
            DROP TRIGGER IF EXISTS ensure_gl_transaction_integrity;
            
            CREATE TRIGGER ensure_gl_transaction_integrity
                BEFORE INSERT ON fin_gl_transaction_headers
                FOR EACH ROW
            BEGIN
                DECLARE period_open BOOLEAN DEFAULT FALSE;
                
                -- Check if fiscal period is open for this transaction type
                SELECT validate_fiscal_period_open(NEW.fiscal_date, NEW.gl_transaction_type) INTO period_open;
                
                IF NOT period_open THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot post transaction to closed fiscal period for this module';
                END IF;
                
                -- Generate GL transaction ID if not provided
                IF NEW.gl_transaction_id IS NULL OR NEW.gl_transaction_id = '' THEN
                    SET NEW.gl_transaction_id = CONCAT(
                        NEW.fiscal_year, '-',
                        LPAD(NEW.gl_transaction_type, 2, '0'), '-',
                        LPAD(NEW.gl_transaction_number, 6, '0')
                    );
                END IF;
                
                -- Validate transaction number is positive
                IF NEW.gl_transaction_number <= 0 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'GL transaction number must be positive';
                END IF;
                
                -- Set default values
                SET NEW.is_balanced = FALSE;
                SET NEW.is_posted = FALSE;
                
            END
        ");
    }
    
    protected function createGlLineIntegrityTriggers()
    {
        DB::unprepared("
            DROP TRIGGER IF EXISTS ensure_gl_line_integrity;
            
            CREATE TRIGGER ensure_gl_line_integrity
                BEFORE INSERT ON fin_gl_transaction_lines
                FOR EACH ROW
            BEGIN
                DECLARE account_active BOOLEAN DEFAULT TRUE;
                DECLARE account_manual_allowed BOOLEAN DEFAULT TRUE;
                DECLARE transaction_type TINYINT;
                
                -- Get transaction type
                SELECT gl_transaction_type INTO transaction_type
                FROM fin_gl_transaction_headers 
                WHERE gl_transaction_id = NEW.gl_transaction_id;
                
                -- Check account status
                SELECT is_active, allow_manual_entry 
                INTO account_active, account_manual_allowed
                FROM fin_gl_accounts 
                WHERE account_id = NEW.account_id;
                
                -- Validate account is active
                IF NOT account_active THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot use inactive account in transaction';
                END IF;
                
                -- Validate manual entry for manual GL transactions
                IF transaction_type = 1 AND NOT account_manual_allowed THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Account does not allow manual entries';
                END IF;
                
                -- Ensure only debit OR credit (not both, not neither)
                IF (NEW.debit_amount > 0 AND NEW.credit_amount > 0) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Line cannot have both debit and credit amounts';
                END IF;
                
                IF (NEW.debit_amount = 0 AND NEW.credit_amount = 0) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Line must have either debit or credit amount';
                END IF;
                
            END;
            
            -- Trigger to update header balance status
            DROP TRIGGER IF EXISTS update_gl_header_balance;
            
            CREATE TRIGGER update_gl_header_balance
                AFTER INSERT ON fin_gl_transaction_lines
                FOR EACH ROW
            BEGIN
                DECLARE is_balanced BOOLEAN;
                
                -- Check if transaction is balanced
                SELECT validate_gl_transaction_balance(NEW.gl_transaction_id) INTO is_balanced;
                
                -- Update header
                UPDATE fin_gl_transaction_headers 
                SET is_balanced = is_balanced,
                    updated_at = NOW()
                WHERE gl_transaction_id = NEW.gl_transaction_id;
                
            END;
            
            -- Also update on UPDATE and DELETE
            DROP TRIGGER IF EXISTS update_gl_header_balance_on_update;
            
            CREATE TRIGGER update_gl_header_balance_on_update
                AFTER UPDATE ON fin_gl_transaction_lines
                FOR EACH ROW
            BEGIN
                DECLARE is_balanced BOOLEAN;
                
                SELECT validate_gl_transaction_balance(NEW.gl_transaction_id) INTO is_balanced;
                
                UPDATE fin_gl_transaction_headers 
                SET is_balanced = is_balanced,
                    updated_at = NOW()
                WHERE gl_transaction_id = NEW.gl_transaction_id;
                
            END;
            
            DROP TRIGGER IF EXISTS update_gl_header_balance_on_delete;
            
            CREATE TRIGGER update_gl_header_balance_on_delete
                AFTER DELETE ON fin_gl_transaction_lines
                FOR EACH ROW
            BEGIN
                DECLARE is_balanced BOOLEAN;
                
                SELECT validate_gl_transaction_balance(OLD.gl_transaction_id) INTO is_balanced;
                
                UPDATE fin_gl_transaction_headers 
                SET is_balanced = is_balanced,
                    updated_at = NOW()
                WHERE gl_transaction_id = OLD.gl_transaction_id;
                
            END
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared('DROP TRIGGER IF EXISTS update_gl_header_balance_on_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS update_gl_header_balance_on_update');
        DB::unprepared('DROP TRIGGER IF EXISTS update_gl_header_balance');
        DB::unprepared('DROP TRIGGER IF EXISTS ensure_gl_line_integrity');
        DB::unprepared('DROP TRIGGER IF EXISTS ensure_gl_transaction_integrity');
        DB::unprepared('DROP FUNCTION IF EXISTS get_next_gl_transaction_number');
        DB::unprepared('DROP FUNCTION IF EXISTS validate_gl_transaction_balance');
        DB::unprepared('DROP FUNCTION IF EXISTS validate_fiscal_period_open');
    }
}
