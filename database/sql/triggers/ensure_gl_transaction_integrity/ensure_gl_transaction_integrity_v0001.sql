DELIMITER $$

DROP TRIGGER IF EXISTS ensure_gl_transaction_integrity$$

CREATE TRIGGER ensure_gl_transaction_integrity
    BEFORE INSERT ON fin_gl_transaction_headers
    FOR EACH ROW
BEGIN
    DECLARE period_open BOOLEAN DEFAULT FALSE;
    DECLARE fiscal_year_val SMALLINT;
    DECLARE transaction_number BIGINT;
    DECLARE fiscal_period_id BIGINT;
    
    -- Check if fiscal period is open for this transaction type
    SELECT validate_fiscal_period_open(NEW.fiscal_date, NEW.gl_transaction_type)
      INTO period_open;
    
    IF NOT period_open THEN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT = 'Cannot post transaction to closed fiscal period for this module';
    END IF;
    
    -- Generate GL transaction ID if not provided
    IF NEW.gl_transaction_id IS NULL OR NEW.gl_transaction_id = '' THEN
        SELECT YEAR(fp.start_date) + 1
          INTO fiscal_year_val
        FROM fin_fiscal_periods fp
        WHERE NEW.fiscal_date BETWEEN fp.start_date AND fp.end_date
        LIMIT 1;
        
        SET transaction_number = get_next_gl_transaction_number(
          'GL_TRANSACTION', fiscal_year_val);
        
        SET NEW.gl_transaction_id = CONCAT(
            fiscal_year_val, '-',
            LPAD(NEW.gl_transaction_type, 2, '0'), '-',
            LPAD(transaction_number, 6, '0')
        );
    END IF;
    
    -- Find and set fiscal period ID
    IF NEW.fiscal_period_id IS NULL THEN
        SELECT id
          INTO fiscal_period_id
        FROM fin_fiscal_periods
        WHERE NEW.fiscal_date BETWEEN start_date AND end_date
          AND deleted_at IS NULL
        LIMIT 1;

        SET NEW.fiscal_period_id = fiscal_period_id;
    END IF;
    
    -- Set default values
    SET NEW.is_balanced = FALSE;
    SET NEW.is_posted = FALSE;
END$$

DELIMITER ;