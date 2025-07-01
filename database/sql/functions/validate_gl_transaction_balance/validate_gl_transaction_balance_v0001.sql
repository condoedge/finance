-- Validate GL transaction balance
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
END;

DROP FUNCTION IF EXISTS get_gl_transaction_out_of_balance_amount;

CREATE FUNCTION get_gl_transaction_out_of_balance_amount(
    p_gl_transaction_id VARCHAR(20)
)
RETURNS DECIMAL(15, 2)
READS SQL DATA
DETERMINISTIC
SQL SECURITY DEFINER
BEGIN
    DECLARE v_total_debits DECIMAL(15, 2) DEFAULT 0.00;
    DECLARE v_total_credits DECIMAL(15, 2) DEFAULT 0.00;
    DECLARE v_out_of_balance DECIMAL(15, 2) DEFAULT 0.00;
    
    -- Calculate total debits and credits
    SELECT 
        COALESCE(SUM(debit_amount), 0.00),
        COALESCE(SUM(credit_amount), 0.00)
    INTO v_total_debits, v_total_credits
    FROM fin_gl_transaction_lines
    WHERE gl_transaction_id = p_gl_transaction_id;
    
    -- Calculate out of balance amount (debit - credit)
    SET v_out_of_balance = v_total_debits - v_total_credits;
    
    RETURN v_out_of_balance;
END;