drop function if exists calculate_total_debits;
CREATE FUNCTION calculate_total_debits(gl_transaction_id INT) RETURNS DECIMAL(19,5)
BEGIN
    DECLARE total_debits DECIMAL(19,5);

    select sum(ifnull(tl.debit_amount, 0)) into total_debits from fin_gl_transaction_lines as tl
    where tl.gl_transaction_id = gl_transaction_id and tl.deleted_at is null;

    if total_debits is null then
        set total_debits = 0;
    end if;

    return total_debits;
END;

drop function if exists calculate_total_credits;
CREATE FUNCTION calculate_total_credits(gl_transaction_id INT) RETURNS DECIMAL(19,5)
BEGIN
    DECLARE total_credits DECIMAL(19,5);

    select sum(ifnull(tl.credit_amount, 0)) into total_credits from fin_gl_transaction_lines as tl
    where tl.gl_transaction_id = gl_transaction_id and tl.deleted_at is null;

    if total_credits is null then
        set total_credits = 0;
    end if;

    return total_credits;
END;