DELIMITER $$

DROP FUNCTION IF EXISTS get_next_gl_transaction_number$$

CREATE FUNCTION get_next_gl_transaction_number(
    p_sequence_name VARCHAR(50),
    p_fiscal_year SMALLINT UNSIGNED
)
RETURNS BIGINT UNSIGNED
READS SQL DATA
MODIFIES SQL DATA
DETERMINISTIC
SQL SECURITY DEFINER
BEGIN
    DECLARE v_next_number BIGINT UNSIGNED DEFAULT 1;
    
    -- Lock the sequence row to prevent concurrent access
    SELECT last_number + 1 INTO v_next_number
    FROM fin_gl_transaction_sequences
    WHERE sequence_name = p_sequence_name 
      AND fiscal_year = p_fiscal_year
    FOR UPDATE;
    
    -- If sequence doesn't exist, create it
    IF v_next_number IS NULL THEN
        INSERT INTO fin_gl_transaction_sequences 
        (sequence_name, fiscal_year, last_number, created_at, updated_at)
        VALUES (p_sequence_name, p_fiscal_year, 1, NOW(), NOW());
        SET v_next_number = 1;
    ELSE
        -- Update the sequence
        UPDATE fin_gl_transaction_sequences 
        SET last_number = v_next_number,
            updated_at = NOW()
        WHERE sequence_name = p_sequence_name 
          AND fiscal_year = p_fiscal_year;
    END IF;
    
    RETURN v_next_number;
END$$

DELIMITER ;
