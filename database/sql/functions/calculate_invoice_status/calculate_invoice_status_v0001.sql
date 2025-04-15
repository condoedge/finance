DELIMITER $$

DROP FUNCTION IF EXISTS calculate_invoice_status$$
CREATE FUNCTION calculate_invoice_status(p_invoice_id INT, p_paid_status_id INT, p_draft_status_id INT, p_pending_status_id INT) RETURNS INT
BEGIN
    DECLARE current_status INT DEFAULT NULL;
    DECLARE items_quantity INT DEFAULT 0;
    DECLARE is_draft BOOLEAN DEFAULT FALSE;

    select count(*) into items_quantity from fin_invoice_details
        where invoice_id = p_invoice_id;

    SELECT invoice_status_id INTO current_status FROM fin_invoices
            WHERE id = p_invoice_id;

    IF is_draft = TRUE THEN
        RETURN p_draft_status_id;
    ELSEIF current_status = p_draft_status_id THEN
        RETURN p_pending_status_id;
    END IF;

    IF calculate_invoice_due(p_invoice_id) = 0 AND items_quantity > 0 THEN
        RETURN p_paid_status_id;
    ELSE 
        IF current_status IS NULL THEN
            RETURN p_draft_status_id;
        END IF;

        RETURN current_status;
    END IF;
END$$

DELIMITER ;