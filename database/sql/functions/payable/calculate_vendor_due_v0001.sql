CREATE OR REPLACE FUNCTION calculate_vendor_due(p_vendor_id BIGINT)
RETURNS DECIMAL(19,5)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE total_bills DECIMAL(19,5) DEFAULT 0;
    DECLARE total_payments DECIMAL(19,5) DEFAULT 0;
    
    -- Calculate total bills amount (considering bill type sign multiplier)
    SELECT COALESCE(SUM(
        CASE 
            WHEN bt.sign_multiplier = 1 THEN b.bill_total_amount
            ELSE -b.bill_total_amount
        END
    ), 0) INTO total_bills
    FROM fin_bills b
    INNER JOIN fin_vendors v ON b.vendor_id = v.id
    INNER JOIN fin_bill_types bt ON b.bill_type_id = bt.id
    WHERE v.id = p_vendor_id
    AND b.bill_status_id != 4; -- Exclude cancelled bills
    
    -- Calculate total payments applied
    SELECT COALESCE(SUM(ba.payment_applied_amount), 0) INTO total_payments
    FROM fin_bill_applies ba
    INNER JOIN fin_bills b ON ba.bill_id = b.id
    INNER JOIN fin_vendors v ON b.vendor_id = v.id
    WHERE v.id = p_vendor_id;
    
    RETURN total_bills - total_payments;
END;
