DELIMITER $$

DROP FUNCTION IF EXISTS get_detail_unit_price_with_sign$$
CREATE FUNCTION get_detail_unit_price_with_sign(id_id BIGINT UNSIGNED) RETURNS DECIMAL(19, 5)
BEGIN
    DECLARE unit_price DECIMAL(19, 5);

    -- Raw unit_price, no sign correction, so rebates stay negative.
    SELECT id.unit_price
    INTO unit_price
    FROM fin_invoice_details id
    WHERE id.id = id_id;

    -- Feeds a NOT NULL column. A row outside this transaction's snapshot matches nothing
    -- above; say so instead of returning NULL or overwriting the line with a made-up 0.
    IF unit_price IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'get_detail_unit_price_with_sign: invoice detail row not readable in this transaction';
    END IF;

    RETURN unit_price;
END$$

DELIMITER ;
