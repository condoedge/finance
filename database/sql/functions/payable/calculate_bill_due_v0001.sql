CREATE OR REPLACE FUNCTION calculate_bill_due(p_bill_id BIGINT)
RETURNS DECIMAL(19,5)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE bill_total DECIMAL(19,5) DEFAULT 0;
    DECLARE payments_applied DECIMAL(19,5) DEFAULT 0;
    DECLARE sign_multiplier INT DEFAULT 1;
    
    -- Get bill total and sign multiplier
    SELECT 
        COALESCE(b.bill_total_amount, 0),
        COALESCE(bt.sign_multiplier, 1)
    INTO bill_total, sign_multiplier
    FROM fin_bills b
    INNER JOIN fin_bill_types bt ON b.bill_type_id = bt.id
    WHERE b.id = p_bill_id;
    
    -- Apply sign multiplier to bill total
    SET bill_total = bill_total * sign_multiplier;
    
    -- Calculate total payments applied to this bill
    SELECT COALESCE(SUM(payment_applied_amount), 0)
    INTO payments_applied
    FROM fin_bill_applies
    WHERE bill_id = p_bill_id;
    
    RETURN bill_total - payments_applied;
END;
