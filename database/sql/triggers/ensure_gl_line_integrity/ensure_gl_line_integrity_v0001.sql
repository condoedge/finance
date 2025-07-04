DELIMITER $$

-- GL Transaction Line integrity triggers

DROP PROCEDURE IF EXISTS validate_gl_line_integrity$$

CREATE PROCEDURE validate_gl_line_integrity(
    IN gl_transaction_id INT, 
    IN account_id INT, 
    IN credit_amount DECIMAL(19,4), 
    IN debit_amount DECIMAL(19,4)
)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE account_active BOOLEAN DEFAULT TRUE;
    DECLARE transaction_type TINYINT;
    DECLARE transaction_posted BOOLEAN DEFAULT FALSE;
    
    -- Check if transaction is already posted
    SELECT is_posted, gl_transaction_type INTO transaction_posted, transaction_type
    FROM fin_gl_transaction_headers 
    WHERE id = gl_transaction_id;
    
    IF transaction_posted THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot modify posted transaction';
    END IF;
    
    -- Check account status
    SELECT is_active 
    INTO account_active
    FROM fin_gl_accounts 
    WHERE id = account_id;
    
    -- Validate account is active
    IF NOT account_active THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot use inactive account in transaction';
    END IF;
    
    -- Ensure only debit OR credit (not both, not neither)
    IF (debit_amount > 0 AND credit_amount > 0) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Line cannot have both debit and credit amounts';
    END IF;
    
    IF (COALESCE(debit_amount, 0) = 0 AND COALESCE(credit_amount, 0) = 0) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Line must have either debit or credit amount';
    END IF;
END$$

DROP TRIGGER IF EXISTS ensure_gl_line_integrity$$

CREATE TRIGGER ensure_gl_line_integrity
    BEFORE INSERT ON fin_gl_transaction_lines
    FOR EACH ROW
BEGIN
    CALL validate_gl_line_integrity(NEW.gl_transaction_id, NEW.account_id, NEW.credit_amount, NEW.debit_amount);
END$$

DROP TRIGGER IF EXISTS ensure_gl_line_integrity_update$$

CREATE TRIGGER ensure_gl_line_integrity_update
    BEFORE UPDATE ON fin_gl_transaction_lines
    FOR EACH ROW
BEGIN
    CALL validate_gl_line_integrity(NEW.gl_transaction_id, NEW.account_id, NEW.credit_amount, NEW.debit_amount);
END$$

DELIMITER ;