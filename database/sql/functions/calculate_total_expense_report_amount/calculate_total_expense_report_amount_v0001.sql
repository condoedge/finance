drop function if exists calculate_total_expense_report_amount;
CREATE FUNCTION calculate_total_expense_report_amount(expense_report_id INT) RETURNS DECIMAL(19,5)
BEGIN
    DECLARE expense_total DECIMAL(19,5);

    SELECT SUM(e.total_expense_amount) INTO expense_total
    FROM fin_expenses AS e
    WHERE e.expense_report_id = expense_report_id;

    RETURN expense_total;
END;