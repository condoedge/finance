DELIMITER $$

DROP FUNCTION IF EXISTS calculate_installment_period_status$$
CREATE FUNCTION calculate_installment_period_status(payment_installment_id INT, p_pending_status INT, p_paid_status INT, p_overdue_status INT) RETURNS INT
BEGIN
    DECLARE pi_due_amount DECIMAL(19, 5);
    DECLARE pi_due_date DATE;

    SELECT calculate_installment_period_due_amount(payment_installment_id) INTO pi_due_amount;

    SELECT due_date INTO pi_due_date FROM fin_payment_installment_periods WHERE id = payment_installment_id;

    IF pi_due_amount = 0 THEN
        RETURN p_paid_status;
    END IF;

    IF pi_due_date < NOW() THEN
        RETURN p_overdue_status;
    END IF;

    RETURN p_pending_status;
END$$

DELIMITER ;