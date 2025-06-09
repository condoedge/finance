DELIMITER $$

DROP FUNCTION IF EXISTS validate_gl_transaction_balance$$

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
END$$

DELIMITER ;
