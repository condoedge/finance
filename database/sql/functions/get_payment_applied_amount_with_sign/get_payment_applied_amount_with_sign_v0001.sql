DELIMITER $$

DROP FUNCTION IF EXISTS get_payment_applied_amount_with_sign$$
CREATE FUNCTION get_payment_applied_amount_with_sign(ip_id INT) RETURNS DECIMAL(19, 5)
BEGIN
    DECLARE applied_amount DECIMAL(19, 5);
    DECLARE invoice_id INT;
    DECLARE sign_multiplier INT DEFAULT 1;

    SELECT ip.invoice_id, ip.payment_applied_amount 
    INTO invoice_id, applied_amount 
    FROM fin_invoice_applies ip
    WHERE ip.id = ip_id;

    return get_amount_using_sign_from_invoice(invoice_id, applied_amount);
END$$

DELIMITER ;