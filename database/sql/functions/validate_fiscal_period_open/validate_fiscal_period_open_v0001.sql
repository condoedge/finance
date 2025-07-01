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
END;