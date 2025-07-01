DELIMITER $$

DROP TRIGGER IF EXISTS ensure_gl_transaction_integrity$$

CREATE TRIGGER ensure_gl_transaction_integrity
    BEFORE INSERT ON fin_gl_transaction_headers
    FOR EACH ROW
BEGIN
    DECLARE period_open BOOLEAN DEFAULT FALSE;
    
    -- Check if fiscal period is open for this transaction type
    SELECT validate_fiscal_period_open(NEW.fiscal_date, NEW.gl_transaction_type)
      INTO period_open;
    
    IF NOT period_open THEN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT = 'Cannot post transaction to closed fiscal period for this module';
    END IF;
  
    -- Set default values
    SET NEW.is_balanced = FALSE;
    SET NEW.is_posted = FALSE;
END$$

DELIMITER ;