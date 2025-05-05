DELIMITER $$

DROP FUNCTION IF EXISTS get_amount_using_sign_from_invoice$$
CREATE FUNCTION get_amount_using_sign_from_invoice(invoice_id INT, amount DECIMAL (19, 5)) RETURNS DECIMAL(19, 5)
BEGIN
    DECLARE sign_multiplier INT DEFAULT 1;

    SELECT it.sign_multiplier 
    INTO sign_multiplier 
    FROM fin_invoices i
    JOIN fin_invoice_types it ON i.invoice_type_id = it.id
    WHERE i.id = invoice_id;

    return get_amount_using_sign_multiplier(amount, sign_multiplier);
END$$

DELIMITER ;