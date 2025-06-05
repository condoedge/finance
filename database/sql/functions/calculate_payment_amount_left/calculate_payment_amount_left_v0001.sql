drop function if exists calculate_payment_amount_left;
CREATE FUNCTION calculate_payment_amount_left(payment_id INT) RETURNS DECIMAL(19,5)
BEGIN
    DECLARE payment_amount_left DECIMAL(19,5);
    DECLARE payment_amount DECIMAL(19,5);
    DECLARE payment_amount_paid DECIMAL(19,5);

    select p.amount into payment_amount from fin_customer_payments as p
    where p.id = payment_id;

    select sum(ifnull(pad.payment_applied_amount, 0)) into payment_amount_paid from fin_invoice_applies as pad
    where pad.applicable_id = payment_id and pad.applicable_type = 1 and pad.deleted_at is null;

    select COALESCE(payment_amount - payment_amount_paid, payment_amount, 0)  into payment_amount_left;
    
    # If they have different sign it means that it's exceding the payment amount so we set it as 0
    if (payment_amount * payment_amount_left < 0) then
        set payment_amount_left = 0;
    end if;

    return payment_amount_left;
END;