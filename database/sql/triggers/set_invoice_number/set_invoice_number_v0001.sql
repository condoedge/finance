CREATE TRIGGER tr_invoice_number_before_insert 
BEFORE INSERT ON fin_invoices
FOR EACH ROW
BEGIN
    IF NEW.invoice_number IS NULL THEN
        SELECT next_number INTO @next_num
        FROM fin_invoice_types 
        WHERE id = NEW.invoice_type_id
        FOR UPDATE;
        
        SET NEW.invoice_number = @next_num;
        
        UPDATE fin_invoice_types 
        SET next_number = next_number + 1
        WHERE id = NEW.invoice_type_id;
    END IF;
END