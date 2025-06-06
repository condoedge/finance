drop function if exists calculate_invoice_tax;
CREATE FUNCTION calculate_invoice_tax(p_invoice_id INT) RETURNS DECIMAL(19,5)
BEGIN
    DECLARE invoice_tax DECIMAL(19,5);
    SELECT SUM(IFNULL(get_detail_tax_amount(in_d.id), 0)) INTO invoice_tax FROM fin_invoices as i
        left join fin_invoice_details as in_d on i.id = in_d.invoice_id and in_d.deleted_at is null
        WHERE i.id = p_invoice_id
        group by i.id;

    IF invoice_tax IS NULL THEN
        SET invoice_tax = 0;
    END IF;

    RETURN invoice_tax;
END;