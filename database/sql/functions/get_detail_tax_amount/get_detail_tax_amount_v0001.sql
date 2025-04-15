DELIMITER $$

DROP FUNCTION IF EXISTS get_detail_tax_amount$$
CREATE FUNCTION get_detail_tax_amount(id_id INT) RETURNS DECIMAL(19, 5)
BEGIN
    DECLARE quantity INT;
    DECLARE unit_price DECIMAL(19, 5);
    DECLARE tax_rate DECIMAL(19, 5);
    DECLARE taxable_amount INT;
    DECLARE tax_group_id INT;

    SELECT get_detail_unit_price_with_sign(id_id) INTO unit_price;
    SELECT quantity INTO quantity
    FROM fin_invoice_details
    WHERE id = id_id;

    select unit_price * quantity into taxable_amount;

    SELECT i.tax_group_id INTO tax_group_id
    FROM fin_invoice_details id
    join fin_invoices i on id.invoice_id = i.id
    WHERE id.id = id_id;

    select sum(tax_rate) into tax_rate
    from fin_taxes_groups tg
    join fin_taxes_group_taxes tgi on tg.id = tgi.tax_group_id
    join fin_taxes t on tgi.tax_id = t.id
    where tg.id = tax_group_id;

    return ABS(taxable_amount * tax_rate / 100);
END$$

DELIMITER ;