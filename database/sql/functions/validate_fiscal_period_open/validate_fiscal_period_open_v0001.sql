-- Validate if fiscal period is open for specific transaction type
DROP FUNCTION IF EXISTS validate_fiscal_period_open;

CREATE FUNCTION validate_fiscal_period_open(
    p_fiscal_date DATE, 
    p_transaction_type TINYINT
) RETURNS BOOLEAN
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE period_open BOOLEAN DEFAULT FALSE;
    DECLARE fiscal_period_id INT;
    
    -- Find the fiscal period for the given date
    SELECT id INTO fiscal_period_id
    FROM fin_fiscal_periods 
    WHERE p_fiscal_date BETWEEN start_date AND end_date
    AND deleted_at IS NULL
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
    WHERE id = fiscal_period_id;
    
    RETURN COALESCE(period_open, FALSE);
END;
