drop function if exists calculate_customer_due;
CREATE FUNCTION calculate_customer_due(p_customer_id INT) RETURNS DECIMAL(19,5)
BEGIN
    DECLARE customer_due DECIMAL(19,5);

    DECLARE customer_total_paid DECIMAL(19,5);
    DECLARE customer_total_debt DECIMAL(19,5);

    SELECT SUM(IFNULL(cp.amount, 0)) INTO customer_total_paid FROM fin_customers as c
        left join fin_customer_payments as cp on c.id = cp.customer_id and cp.deleted_at is null
        WHERE c.id = p_customer_id
        group by c.id;

    SELECT SUM(IFNULL(ci.invoice_total_amount, 0)) INTO customer_total_debt FROM fin_customers as c
        left join fin_invoices as ci on c.id = ci.customer_id and ci.deleted_at is null and ci.invoice_status_id != 1 and ci.invoice_status_id != 4
        WHERE c.id = p_customer_id
        group by c.id;
    
    select IFNULL(customer_total_debt, 0) - IFNULL(customer_total_paid, 0) into customer_due;

    IF customer_due IS NULL THEN
        SET customer_due = 0;
    END IF;

    RETURN customer_due;
END;