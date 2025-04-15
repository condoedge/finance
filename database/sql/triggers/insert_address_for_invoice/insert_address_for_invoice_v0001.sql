DELIMITER $$

CREATE TRIGGER trg_insert_address_for_invoice
AFTER INSERT ON fin_invoices
FOR EACH ROW
BEGIN
    INSERT INTO addresses (addressable_id, addressable_type, address1, city, state, postal_code, country, created_at, updated_at)
    select NEW.id, 'invoice', a.address1, a.city, a.state, a.postal_code, a.country, NOW(), NOW() from addresses as a
    join fin_customers c on c.id = NEW.customer_id
    where a.id = c.default_billing_address_id;

END $$

DELIMITER ;