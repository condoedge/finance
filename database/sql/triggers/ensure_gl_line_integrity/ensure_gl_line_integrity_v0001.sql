DELIMITER $$

DROP TRIGGER IF EXISTS ensure_gl_line_integrity$$

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
    
END$$

-- Trigger to update header balance status
DROP TRIGGER IF EXISTS update_gl_header_balance$$

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
    
END$$

-- Also update on UPDATE and DELETE
DROP TRIGGER IF EXISTS update_gl_header_balance_on_update$$

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
    
END$$

DROP TRIGGER IF EXISTS update_gl_header_balance_on_delete$$

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
    
END$$

DELIMITER ;
