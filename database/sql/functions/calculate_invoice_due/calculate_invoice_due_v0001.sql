drop function if exists calculate_invoice_due;
CREATE FUNCTION calculate_invoice_due(p_invoice_id INT) RETURNS DECIMAL(19,5)
BEGIN
    DECLARE invoice_due DECIMAL(19,5);
    SELECT SUM(-IFNULL(ip.payment_applied_amount, 0) + IFNULL(in_d.total_amount, 0)) INTO invoice_due FROM fin_invoices as i
        left join fin_invoice_details as in_d on i.id = in_d.invoice_id and in_d.deleted_at is null
        left join fin_invoice_payments as ip on i.id = ip.invoice_id and ip.deleted_at is null
        WHERE i.id = p_invoice_id
        group by i.id;

    IF invoice_due IS NULL THEN
        SET invoice_due = 0;
    END IF;

    RETURN invoice_due;
END;