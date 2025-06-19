-- Get next GL transaction number from sequence
DROP FUNCTION IF EXISTS get_next_gl_transaction_number;

CREATE FUNCTION get_next_gl_transaction_number(
    p_sequence_name VARCHAR(50), 
    p_fiscal_year SMALLINT
) 
RETURNS BIGINT
MODIFIES SQL DATA
NOT DETERMINISTIC
BEGIN
    DECLARE next_number BIGINT;
    
    -- Insert or update sequence with atomic increment
    INSERT INTO fin_gl_transaction_sequences (sequence_name, fiscal_year, last_number, created_at, updated_at)
    VALUES (p_sequence_name, p_fiscal_year, 1, NOW(), NOW())
    ON DUPLICATE KEY UPDATE 
        last_number = last_number + 1,
        updated_at = NOW();
    
    -- Get the current number
    SELECT last_number INTO next_number
    FROM fin_gl_transaction_sequences 
    WHERE sequence_name = p_sequence_name 
    AND fiscal_year = p_fiscal_year;
    
    RETURN next_number;
END;
