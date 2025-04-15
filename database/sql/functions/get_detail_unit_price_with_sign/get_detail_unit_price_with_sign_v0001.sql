DELIMITER $$

DROP FUNCTION IF EXISTS get_detail_unit_price_with_sign$$
CREATE FUNCTION get_detail_unit_price_with_sign(id_id INT) RETURNS DECIMAL(19, 5)
BEGIN
    DECLARE unit_price DECIMAL(19, 5);
    DECLARE invoice_id INT;
    DECLARE sign_multiplier INT DEFAULT 1;

    SELECT id.invoice_id, id.unit_price 
    INTO invoice_id, unit_price 
    FROM fin_invoice_details id
    WHERE id.id = id_id;

    SELECT it.sign_multiplier 
    INTO sign_multiplier 
    FROM fin_invoices i
    JOIN fin_invoice_types it ON i.invoice_type_id = it.id
    WHERE i.id = invoice_id;

    IF unit_price IS NULL THEN
        RETURN 0.00;
    END IF;

    IF unit_price / sign_multiplier < 0 THEN
        RETURN unit_price * -1;
    END IF;

    RETURN unit_price;
END$$

DELIMITER ;