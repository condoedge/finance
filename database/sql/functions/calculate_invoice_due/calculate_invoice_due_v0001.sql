drop function if exists calculate_invoice_total_paid;
CREATE FUNCTION calculate_invoice_total_paid(p_invoice_id INT) RETURNS DECIMAL(19,5)
BEGIN
    DECLARE total_paid DECIMAL(19,5) DEFAULT 0;

    SELECT SUM(IFNULL(ip.payment_applied_amount, 0)) INTO total_paid FROM fin_invoice_applies as ip
    WHERE ip.invoice_id = p_invoice_id and ip.deleted_at IS NULL;

    IF total_paid IS NULL THEN
        SET total_paid = 0;
    END IF;

    RETURN total_paid;
END;

drop function if exists calculate_invoice_due;
CREATE FUNCTION calculate_invoice_due(p_invoice_id INT) RETURNS DECIMAL(19,5)
BEGIN
    DECLARE credit_applicable_type INT DEFAULT 2; -- 2 = Credit Note
    DECLARE invoice_due DECIMAL(19,5);
    DECLARE invoice_total_paid DECIMAL(19,5);
    DECLARE invoice_total_substract DECIMAL(19,5);
    DECLARE invoice_total DECIMAL(19,5);

    SELECT calculate_invoice_amount_before_taxes(p_invoice_id) + calculate_invoice_tax(p_invoice_id) INTO invoice_total;

    # If payments or credits are applied to the invoice, we need to subtract them from the total amount.
    SELECT IFNULL(calculate_invoice_total_paid(p_invoice_id), 0) INTO invoice_total_paid;

    # If it's a credit note, we need to subtract the amount from the total amount.
    SELECT SUM(- IFNULL(ip.payment_applied_amount, 0)) INTO invoice_total_substract FROM fin_invoice_applies as ip
    WHERE ip.applicable_type = credit_applicable_type and ip.applicable_id = p_invoice_id and ip.deleted_at IS NULL;

    select IFNULL(invoice_total - IFNULL(invoice_total_paid, 0) - IFNULL(invoice_total_substract, 0), 0) into invoice_due;

    IF invoice_due IS NULL THEN
        SET invoice_due = 0;
    END IF;

    RETURN invoice_due;
END;