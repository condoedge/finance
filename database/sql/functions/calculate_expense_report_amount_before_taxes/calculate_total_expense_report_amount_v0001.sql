drop function if exists calculate_expense_report_amount_before_taxes;
CREATE FUNCTION calculate_expense_report_amount_before_taxes(expense_report_id INT) RETURNS DECIMAL(19,5)
BEGIN
    DECLARE expense_amount_before_taxes DECIMAL(19,5);

    SELECT SUM(e.expense_amount_before_taxes) INTO expense_amount_before_taxes
    FROM fin_expenses AS e
    WHERE e.expense_report_id = expense_report_id;

    RETURN expense_amount_before_taxes;
END;