DELIMITER $$

DROP FUNCTION IF EXISTS get_next_gl_transaction_number$$

CREATE FUNCTION get_next_gl_transaction_number(p_sequence_type VARCHAR(20), p_fiscal_year SMALLINT) 
RETURNS BIGINT
MODIFIES SQL DATA
DETERMINISTIC
BEGIN
    DECLARE next_number BIGINT;
    
    -- Get and increment the sequence in a single atomic operation
    UPDATE fin_gl_sequences 
    SET last_number = last_number + 1,
        updated_at = NOW()
    WHERE sequence_type = p_sequence_type 
    AND (fiscal_year = p_fiscal_year OR (fiscal_year IS NULL AND p_fiscal_year IS NULL));
    
    -- Get the new number
    SELECT last_number INTO next_number
    FROM fin_gl_sequences 
    WHERE sequence_type = p_sequence_type 
    AND (fiscal_year = p_fiscal_year OR (fiscal_year IS NULL AND p_fiscal_year IS NULL));
    
    RETURN next_number;
END$$

DELIMITER ;
