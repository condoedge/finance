-- GL Transaction Line integrity triggers
DROP TRIGGER IF EXISTS ensure_gl_line_integrity;

CREATE TRIGGER ensure_gl_line_integrity
    BEFORE INSERT ON fin_gl_transaction_lines
    FOR EACH ROW
BEGIN
    DECLARE account_active BOOLEAN DEFAULT TRUE;
    DECLARE transaction_type TINYINT;
    DECLARE transaction_posted BOOLEAN DEFAULT FALSE;
    
    -- Check if transaction is already posted
    SELECT is_posted, gl_transaction_type INTO transaction_posted, transaction_type
    FROM fin_gl_transaction_headers 
    WHERE id = NEW.gl_transaction_id;
    
    IF transaction_posted THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot modify posted transaction';
    END IF;
    
    -- Check account status
    SELECT is_active 
    INTO account_active
    FROM fin_gl_accounts 
    WHERE id = NEW.account_id;
    
    -- Validate account is active
    IF NOT account_active THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot use inactive account in transaction';
    END IF;
    
    -- Ensure only debit OR credit (not both, not neither)
    IF (NEW.debit_amount > 0 AND NEW.credit_amount > 0) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Line cannot have both debit and credit amounts';
    END IF;
    
    IF (COALESCE(NEW.debit_amount, 0) = 0 AND COALESCE(NEW.credit_amount, 0) = 0) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Line must have either debit or credit amount';
    END IF;
END;

-- Update header balance status after line insert
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
    WHERE id = NEW.gl_transaction_id;
END;

-- Update header balance status after line update
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
    WHERE id = NEW.gl_transaction_id;
END;

-- Update header balance status after line delete
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
    WHERE id = OLD.gl_transaction_id;
END;
