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

CREATE TRIGGER ensure_gl_transaction_integrity_update
    BEFORE UPDATE ON fin_gl_transaction_headers
    FOR EACH ROW
BEGIN
    DECLARE changes_detected VARCHAR(500) DEFAULT '';
    
    -- Check if important fields are changing
    IF OLD.is_posted != NEW.is_posted THEN
        SET changes_detected = CONCAT(changes_detected, 'is_posted,');
    END IF;
    
    IF OLD.fiscal_date != NEW.fiscal_date THEN
        SET changes_detected = CONCAT(changes_detected, 'fiscal_date,');
    END IF;

    IF OLD.transaction_description != NEW.transaction_description THEN
        SET changes_detected = CONCAT(changes_detected, 'transaction_description,');
    END IF;

    IF OLD.gl_transaction_number != NEW.gl_transaction_number THEN
        SET changes_detected = CONCAT(changes_detected, 'gl_transaction_number,');
    END IF;

    IF OLD.gl_transaction_type != NEW.gl_transaction_type THEN
        SET changes_detected = CONCAT(changes_detected, 'gl_transaction_type,');
    END IF;

    IF OLD.fiscal_period_id != NEW.fiscal_period_id THEN
        SET changes_detected = CONCAT(changes_detected, 'fiscal_period_id,');
    END IF;

    IF OLD.vendor_id != NEW.vendor_id THEN
        SET changes_detected = CONCAT(changes_detected, 'vendor_id,');
    END IF;

    IF OLD.customer_id != NEW.customer_id THEN
        SET changes_detected = CONCAT(changes_detected, 'customer_id,');
    END IF;

    -- Allowing just in the case is balance update
    IF OLD.is_posted = TRUE AND changes_detected != '' THEN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT = 'Cannot modify posted transaction';
    END IF;
END$$

DELIMITER ;