DELIMITER $$

DROP FUNCTION IF EXISTS get_detail_unit_price_with_sign$$
CREATE FUNCTION get_detail_unit_price_with_sign(id_id INT) RETURNS DECIMAL(19, 5)
BEGIN
    DECLARE unit_price DECIMAL(19, 5);

    -- Simply return the raw unit_price without sign correction
    -- This allows negative values (rebates) to be preserved as-is
    SELECT id.unit_price
    INTO unit_price
    FROM fin_invoice_details id
    WHERE id.id = id_id;

    RETURN unit_price;
END$$

DELIMITER ;