DELIMITER $$
DROP FUNCTION IF EXISTS get_updated_tax_amount_for_taxes$$
CREATE FUNCTION get_updated_tax_amount_for_taxes(p_detail_id INT, tax_rate DECIMAL (19, 5)) RETURNS DECIMAL(19,5)
BEGIN
    DECLARE unit_price    DECIMAL(19,5);
    DECLARE quantity      INT;
    DECLARE taxable_amount DECIMAL(19,5);

    SELECT get_detail_unit_price_with_sign(p_detail_id) INTO unit_price;
    SELECT d.quantity INTO quantity
      FROM fin_invoice_details d
     WHERE d.id = p_detail_id;

    SET taxable_amount = unit_price * quantity;

    RETURN COALESCE(taxable_amount * tax_rate, 0);
END $$