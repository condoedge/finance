CREATE OR REPLACE FUNCTION calculate_vendor_payment_amount_left(p_payment_id BIGINT)
RETURNS DECIMAL(19,5)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE payment_amount DECIMAL(19,5) DEFAULT 0;
    DECLARE applied_amount DECIMAL(19,5) DEFAULT 0;
    
    -- Get original payment amount
    SELECT COALESCE(amount, 0)
    INTO payment_amount
    FROM fin_vendor_payments
    WHERE id = p_payment_id;
    
    -- Calculate total amount applied from this payment
    SELECT COALESCE(SUM(payment_applied_amount), 0)
    INTO applied_amount
    FROM fin_bill_applies
    WHERE applicable_id = p_payment_id
    AND applicable_type = 1; -- Assuming 1 is the type for vendor payments
    
    RETURN payment_amount - applied_amount;
END;
