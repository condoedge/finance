drop function if exists calculate_product_taxes_amount;
CREATE FUNCTION calculate_product_taxes_amount(product_id INT) RETURNS DECIMAL(19,5)
BEGIN
    DECLARE product_tax_amount DECIMAL(19,5);
    DECLARE product_taxes_ids json;
    DECLARE product_cost DECIMAL(19,5);

    SELECT p.taxes_ids, p.product_cost INTO product_taxes_ids, product_cost
    FROM fin_products p
    WHERE id = product_id;

    SELECT SUM(t.rate * product_cost) INTO product_tax_amount
    FROM fin_taxes as t
    WHERE JSON_CONTAINS(product_taxes_ids, CAST(t.id as json), '$');

    IF product_tax_amount IS NULL THEN
        SET product_tax_amount = 0;
    END IF;

    RETURN product_tax_amount;
END;