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

    return get_amount_using_sign_from_invoice(invoice_id, unit_price);
END$$

DELIMITER ;