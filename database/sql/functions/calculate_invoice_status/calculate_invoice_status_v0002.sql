DELIMITER $$

DROP FUNCTION IF EXISTS get_invoice_status_id$$
CREATE FUNCTION get_invoice_status_id(p_status_code VARCHAR(255) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci) RETURNS INT
BEGIN
    DECLARE status_id INT DEFAULT NULL;

    SELECT id INTO status_id FROM fin_invoice_statuses
    WHERE code = p_status_code;

    RETURN status_id;
END$$

DROP FUNCTION IF EXISTS is_invoice_overdue$$
CREATE FUNCTION is_invoice_overdue(p_invoice_id INT) RETURNS BOOLEAN
BEGIN
    DECLARE due_date DATETIME DEFAULT NULL;
    DECLARE overdue_installments INT DEFAULT 0;

    SELECT invoice_due_date INTO due_date FROM fin_invoices
    WHERE id = p_invoice_id;

    SELECT SUM(IF(calculate_installment_period_status(pip.id, 1, 2, 3) = 3, 1, 0)) INTO overdue_installments FROM fin_payment_installment_periods pip
    WHERE invoice_id = p_invoice_id AND deleted_at IS NULL;

    IF overdue_installments IS NULL THEN
        SET overdue_installments = 0;
    END IF;

    RETURN due_date < NOW() OR overdue_installments > 0;
END$$

DROP FUNCTION IF EXISTS calculate_invoice_status$$
CREATE FUNCTION calculate_invoice_status(p_invoice_id INT) RETURNS INT
BEGIN
    DECLARE current_status INT DEFAULT NULL;
    DECLARE items_quantity INT DEFAULT 0;
    DECLARE is_draft BOOLEAN DEFAULT TRUE;
    DECLARE p_invoice_due_date DATETIME DEFAULT NULL;

    select count(*) into items_quantity from fin_invoice_details
        where invoice_id = p_invoice_id;

    SELECT invoice_status_id, invoice_due_date INTO current_status, p_invoice_due_date FROM fin_invoices
            WHERE id = p_invoice_id;

    SELECT fin_invoices.is_draft = 1 INTO is_draft FROM fin_invoices
            WHERE id = p_invoice_id;

    IF is_draft = TRUE THEN
        RETURN get_invoice_status_id('draft');
    ELSEIF current_status = get_invoice_status_id('draft') THEN
        IF is_invoice_overdue(p_invoice_id)> 0 THEN
            RETURN get_invoice_status_id('overdue');
        END IF;

        RETURN get_invoice_status_id('pending');
    END IF;

    IF calculate_invoice_due(p_invoice_id) = 0 AND items_quantity > 0 THEN
        RETURN get_invoice_status_id('paid');
    ELSE
        IF current_status IS NULL THEN
            RETURN get_invoice_status_id('draft');
        END IF;

        IF is_invoice_overdue(p_invoice_id) > 0 THEN
            RETURN get_invoice_status_id('overdue');
        ELSEIF current_status = get_invoice_status_id('overdue') THEN
            RETURN IF(calculate_invoice_total_paid(p_invoice_id) = 0, get_invoice_status_id('pending'), get_invoice_status_id('partial'));
        END IF;

        IF current_status = get_invoice_status_id('paid') THEN
            RETURN IF(calculate_invoice_total_paid(p_invoice_id) = 0, get_invoice_status_id('pending'), get_invoice_status_id('partial'));
        END IF;

        IF current_status = get_invoice_status_id('pending') AND calculate_invoice_total_paid(p_invoice_id) > 0 THEN
            RETURN get_invoice_status_id('partial');
        END IF;

        RETURN current_status;
    END IF;
END$$

DELIMITER ;