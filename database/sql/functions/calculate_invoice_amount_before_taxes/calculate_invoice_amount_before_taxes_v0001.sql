drop function if exists calculate_invoice_amount_before_taxes;
CREATE FUNCTION calculate_invoice_amount_before_taxes(p_invoice_id INT) RETURNS DECIMAL(19,5)
BEGIN
    DECLARE invoice_amount_before_taxes DECIMAL(19,5);
    SELECT SUM(IFNULL(in_d.extended_price, 0)) INTO invoice_amount_before_taxes FROM fin_invoices as i
        left join fin_invoice_details as in_d on i.id = in_d.invoice_id
        WHERE i.id = p_invoice_id and in_d.deleted_at is null
        group by i.id;

    IF invoice_amount_before_taxes IS NULL THEN
        SET invoice_amount_before_taxes = 0;
    END IF;

    RETURN invoice_amount_before_taxes;
END;