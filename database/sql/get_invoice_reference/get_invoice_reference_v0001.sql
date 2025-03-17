drop function if exists get_invoice_reference;
CREATE FUNCTION get_invoice_reference(p_invoice_id INT) RETURNS VARCHAR(255)
BEGIN
    DECLARE invoice_reference VARCHAR(255);
    SELECT CONCAT(it.prefix, '-', LPAD(i.invoice_number, 8, '0')) INTO invoice_reference FROM fin_invoices as i
        left join fin_invoice_types as it on i.invoice_type_id = it.id
        WHERE i.id = p_invoice_id;

    RETURN invoice_reference;
END;