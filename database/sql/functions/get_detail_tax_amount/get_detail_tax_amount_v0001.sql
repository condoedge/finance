DELIMITER $$

DROP FUNCTION IF EXISTS get_detail_tax_amount$$
CREATE FUNCTION get_detail_tax_amount(id_id INT) RETURNS DECIMAL(19, 5)
BEGIN
    DECLARE tax_amount DECIMAL(19, 5);

    SELECT SUM(idt.`tax_amount`) INTO tax_amount
    FROM fin_invoice_detail_taxes idt
    WHERE idt.invoice_detail_id = id_id and deleted_at is null;

    IF tax_amount IS NULL THEN
        SET tax_amount = 0.0;
    END IF;

    RETURN tax_amount;
END$$

DELIMITER ;