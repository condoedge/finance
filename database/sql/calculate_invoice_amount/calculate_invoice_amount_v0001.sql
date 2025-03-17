drop function if exists calculate_invoice_amount;
CREATE FUNCTION calculate_invoice_amount(p_invoice_id INT) RETURNS DECIMAL(19,5)
BEGIN
    DECLARE invoice_amount DECIMAL(19,5);
    SELECT SUM(IFNULL(in_d.extended_price, 0)) INTO invoice_amount FROM fin_invoices as i
        left join fin_invoice_details as in_d on i.id = in_d.invoice_id
        WHERE i.id = p_invoice_id
        group by i.id;

    IF invoice_amount IS NULL THEN
        SET invoice_amount = 0;
    END IF;

    RETURN invoice_amount;
END;