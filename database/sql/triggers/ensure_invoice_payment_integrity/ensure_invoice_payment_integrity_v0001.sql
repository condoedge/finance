drop trigger  trg_ensure_invoice_payment_integrity;
DELIMITER $$

CREATE TRIGGER trg_ensure_invoice_payment_integrity
BEFORE INSERT ON fin_invoice_applies
FOR EACH ROW
BEGIN
    DECLARE payment_left DECIMAL(19, 5);
    DECLARE invoice_left DECIMAL(19, 5);
    DECLARE applied_amount DECIMAL(19, 5);

    # PAYMENT = 1
    # INVOICE = 2

    if NEW.applicable_type = 1 then
        select calculate_payment_amount_left(NEW.applicable_id) into payment_left;
    end if;
    
    if NEW.applicable_type = 2 then
        select abs(calculate_invoice_due(NEW.applicable_id)) into payment_left;
    end if;

    select calculate_invoice_due(NEW.invoice_id) into invoice_left;

    select get_amount_using_sign_from_invoice(NEW.invoice_id, NEW.payment_applied_amount) into applied_amount;

    if payment_left - applied_amount < 0 then
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment amount exceeds the payment left.';
    end if;

    if invoice_left - applied_amount < 0 then
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment amount exceeds the invoice left.';
    end if;

    if applied_amount = 0 then
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment amount must be greater than zero.';
    end if;
END $$

DELIMITER ;