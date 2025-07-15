DELIMITER $$

CREATE TRIGGER trg_insert_historical_customer
BEFORE INSERT ON fin_invoices
FOR EACH ROW
BEGIN
    INSERT INTO fin_historical_customers (customer_id, name, email, team_id, created_at, updated_at)
    SELECT id, name, email, team_id, NOW(), NOW()
    FROM fin_customers
    WHERE id = NEW.customer_id;

    SET NEW.historical_customer_id = LAST_INSERT_ID();
END $$

DELIMITER ;