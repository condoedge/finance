DELIMITER $$

CREATE TRIGGER prevent_update_invoice_customer
BEFORE UPDATE ON fin_invoices
FOR EACH ROW
BEGIN
    IF OLD.customer_id != NEW.customer_id OR OLD.historical_customer_id != NEW.historical_customer_id THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Updating customer_id or historical_customer_id is not allowed';
    END IF;
END $$

CREATE TRIGGER prevent_modification_fin_historical_customers
BEFORE UPDATE ON fin_historical_customers
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Modifying fin_historical_customers is not allowed';
END $$

CREATE TRIGGER prevent_delete_fin_historical_customers
BEFORE DELETE ON fin_historical_customers
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Deleting fin_historical_customers is not allowed';
END $$

DELIMITER ;