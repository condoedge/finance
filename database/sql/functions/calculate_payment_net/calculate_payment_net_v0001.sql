drop function if exists calculate_payment_net;
CREATE FUNCTION calculate_payment_net(payment_id INT) RETURNS DECIMAL(19,5) DETERMINISTIC
BEGIN
    DECLARE payment_net DECIMAL(19,5);

    select (p.amount - COALESCE(p.processor_fees, 0)) into payment_net
    from fin_customer_payments as p
    where p.id = payment_id;

    return COALESCE(payment_net, 0);
END;
