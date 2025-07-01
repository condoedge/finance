CREATE TRIGGER tr_transaction_number_before_insert 
BEFORE INSERT ON fin_gl_transaction_headers
FOR EACH ROW
BEGIN
    IF NEW.gl_transaction_number IS NULL THEN
        SELECT next_number INTO @next_num
        FROM fin_gl_transaction_types
        WHERE id = NEW.gl_transaction_type
        FOR UPDATE;

        SET NEW.gl_transaction_number = @next_num;

        UPDATE fin_gl_transaction_types
        SET next_number = next_number + 1
        WHERE id = NEW.gl_transaction_type;
    END IF;
END