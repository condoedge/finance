drop trigger if exists trg_ensure_invoice_payment_integrity;
DELIMITER $$

CREATE TRIGGER trg_ensure_invoice_payment_integrity
BEFORE INSERT ON fin_invoice_applies
FOR EACH ROW
BEGIN
    DECLARE payment_left DECIMAL(19, 5);
    DECLARE invoice_left DECIMAL(19, 5);
    DECLARE applied_amount DECIMAL(19, 5);
    DECLARE payment_left_after_apply DECIMAL(19, 5);
    DECLARE invoice_sign_multiplier INT;
    DECLARE applicable_sign_check DECIMAL(19, 5);

    # PAYMENT = 1
    # INVOICE = 2

    # Get the amount left for the applicable (payment or invoice)
    if NEW.applicable_type = 1 then
        select calculate_payment_amount_left(NEW.applicable_id) into payment_left;
    end if;
    
    if NEW.applicable_type = 2 then
        select abs(calculate_invoice_due(NEW.applicable_id)) into payment_left;
    end if;

    # Get invoice due amount
    select calculate_invoice_due(NEW.invoice_id) into invoice_left;

    # Get the applied amount with proper signs
    select get_amount_using_sign_from_invoice(NEW.invoice_id, NEW.payment_applied_amount) into applied_amount;

    # Get invoice sign multiplier for validation
    select sign_multiplier into invoice_sign_multiplier 
    from fin_invoice_types it
    join fin_invoices i on i.invoice_type_id = it.id
    where i.id = NEW.invoice_id;

    # Check 1: Payment amount must be greater than zero
    if applied_amount = 0 then
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment amount must be greater than zero.';
    end if;

    # Check 2: Applied amount cannot exceed payment left
    # Using the same logic as PHP: payment_left * (payment_left - applied_amount) < 0
    if payment_left * (payment_left - applied_amount) < 0 then
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment amount exceeds the payment left.';
    end if;

    # Check 3: Applied amount cannot exceed invoice left
    if invoice_left * (invoice_left - applied_amount) < 0 then
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment amount exceeds the invoice left.';
    end if;

    # Check 4: Sign compatibility check (NEW - matches your PHP logic)
    # You cannot apply a negative payment to a positive invoice
    # This prevents applying credit payments to invoices or positive payments to credit invoices
    set applicable_sign_check = payment_left * invoice_sign_multiplier;
    if applicable_sign_check < 0 then
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot apply negative payment to positive invoice or positive payment to credit invoice.';
    end if;

    # Check 5: Invoice cannot be a draft
    if (select is_draft from fin_invoices where id = NEW.invoice_id) then
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot apply payment to a draft invoice.';
    end if;
END $$

DELIMITER ;