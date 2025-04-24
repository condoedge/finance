drop function if exists calculate_customer_due;
CREATE FUNCTION calculate_customer_due(p_customer_id INT) RETURNS DECIMAL(19,5)
BEGIN
    DECLARE customer_due DECIMAL(19,5);
    SELECT SUM(-IFNULL(cp.amount, 0) + IFNULL(ci.invoice_total_amount, 0)) INTO customer_due FROM fin_customers as c
        left join fin_customer_payments as cp on c.id = cp.customer_id
        left join fin_invoices as ci on c.id = ci.customer_id
        WHERE c.id = p_customer_id
        group by c.id;

    IF customer_due IS NULL THEN
        SET customer_due = 0;
    END IF;

    RETURN customer_due;
END;