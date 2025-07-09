drop function if exists calculate_installment_period_due_amount;
CREATE FUNCTION calculate_installment_period_due_amount(p_installent_period_id INT) RETURNS DECIMAL(19,5)
BEGIN
    DECLARE period_due_amount DECIMAL(19,5);
    DECLARE invoice_total_amount DECIMAL(19,5);
    DECLARE invoice_due DECIMAL(19,5);
    DECLARE total_amount_of_current_period DECIMAL(19,5);
    DECLARE period_number INT;
    DECLARE p_invoice_id INT;
    DECLARE previous_amount_to_be_covered DECIMAL(19, 5);
    DECLARE total_paid DECIMAL (19, 5);

    SELECT calculate_invoice_due(pip.invoice_id), installment_number, amount, invoice_id into invoice_due,
           period_number, total_amount_of_current_period, p_invoice_id
    from fin_payment_installment_periods pip
    where pip.id = p_installent_period_id;

    SELECT i.invoice_total_amount into invoice_total_amount
    from fin_invoices i where i.id = p_invoice_id;

    IF invoice_due IS NULL THEN
        SET invoice_due = 0;
    END IF;

    IF invoice_total_amount IS NULL THEN
        SET invoice_total_amount = 0;
    END IF;

    SELECT invoice_total_amount - invoice_due INTO total_paid;

    SELECT SUM(pip.amount) INTO previous_amount_to_be_covered
    from fin_payment_installment_periods pip
    where pip.installment_number < period_number
    AND pip.invoice_id = p_invoice_id and deleted_at is null;

    IF total_paid <= previous_amount_to_be_covered THEN
        RETURN IFNULL(total_amount_of_current_period, 0);
    END IF;

    SET period_due_amount = LEAST(total_amount_of_current_period - (IFNULL(total_paid, 0) - IFNULL(previous_amount_to_be_covered, 0)),
                                   total_amount_of_current_period);

    IF period_due_amount < 0 THEN
        SET period_due_amount = 0;
    END IF;

    RETURN period_due_amount;
END;

select calculate_installment_period_due_amount(24)