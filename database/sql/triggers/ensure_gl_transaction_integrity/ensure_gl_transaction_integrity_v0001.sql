DELIMITER $$

DROP TRIGGER IF EXISTS ensure_gl_transaction_integrity$$

CREATE TRIGGER ensure_gl_transaction_integrity
    BEFORE INSERT ON fin_gl_transaction_headers
    FOR EACH ROW
BEGIN
    DECLARE period_open BOOLEAN DEFAULT FALSE;
    DECLARE account_active BOOLEAN DEFAULT TRUE;
    DECLARE account_manual_allowed BOOLEAN DEFAULT TRUE;
    
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
    
    -- Validate transaction number is sequential (basic check)
    IF NEW.gl_transaction_number <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'GL transaction number must be positive';
    END IF;
    
    -- Set default values
    SET NEW.is_balanced = FALSE;
    SET NEW.is_posted = FALSE;
    
END$$

DELIMITER ;
