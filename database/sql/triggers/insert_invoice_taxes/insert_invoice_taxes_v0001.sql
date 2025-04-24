DELIMITER $$
DROP FUNCTION IF EXISTS get_updated_tax_amount_for_taxes$$
CREATE FUNCTION get_updated_tax_amount_for_taxes(p_detail_id INT, tax_rate DECIMAL (19, 5)) RETURNS DECIMAL(19,5)
BEGIN
    DECLARE unit_price    DECIMAL(19,5);
    DECLARE quantity      INT;
    DECLARE taxable_amount DECIMAL(19,5);

    SELECT get_detail_unit_price_with_sign(p_detail_id) INTO unit_price;
    SELECT d.quantity INTO quantity
      FROM fin_invoice_details d
     WHERE d.id = p_detail_id;

    SET taxable_amount = unit_price * quantity;

    RETURN COALESCE(taxable_amount * tax_rate, 0);
END $$

DELIMITER $$
DROP PROCEDURE IF EXISTS sp_insert_invoice_detail_tax$$
CREATE PROCEDURE sp_insert_invoice_detail_tax(IN p_detail_id INT)
BEGIN
    DECLARE unit_price    DECIMAL(19,5);
    DECLARE quantity      INT;
    DECLARE taxable_amount DECIMAL(19,5);
    DECLARE tax_group_id  INT;

    SELECT i.tax_group_id INTO tax_group_id
      FROM fin_invoice_details d
      JOIN fin_invoices i ON d.invoice_id = i.id
     WHERE d.id = p_detail_id;

    delete from fin_invoice_detail_taxes where invoice_detail_id = p_detail_id;

    INSERT INTO fin_invoice_detail_taxes
      (invoice_detail_id, tax_id, tax_rate, tax_amount, created_at, updated_at)
    SELECT
      p_detail_id,
      t.id,
      t.rate,
      get_updated_tax_amount_for_taxes(p_detail_id, t.rate),
      NOW(),
      NOW()
    FROM fin_taxes_group_taxes tgt
    JOIN fin_taxes t  ON t.id = tgt.tax_id
    WHERE tgt.tax_group_id = tax_group_id;
END $$

DELIMITER $$
CREATE PROCEDURE sp_recalc_invoice_details_by_invoice(IN p_invoice_id INT)
BEGIN
    DECLARE done   BOOL    DEFAULT FALSE;
    DECLARE d_id   INT;
    DECLARE cur    CURSOR FOR
        SELECT id FROM fin_invoice_details WHERE invoice_id = p_invoice_id;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO d_id;
        IF done THEN
            LEAVE read_loop;
        END IF;
        CALL sp_insert_invoice_detail_tax(d_id);
    END LOOP;
    CLOSE cur;
END$$
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE sp_recalc_all_invoice_details()
BEGIN
    DECLARE done   BOOL    DEFAULT FALSE;
    DECLARE d_id   INT;
    DECLARE cur    CURSOR FOR
        SELECT id FROM fin_invoice_details;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO d_id;
        IF done THEN
            LEAVE read_loop;
        END IF;
        CALL sp_insert_invoice_detail_tax(d_id);
    END LOOP;
    CLOSE cur;
END$$
DELIMITER ;

DELIMITER ;
DROP TRIGGER IF EXISTS tr_invoice_details_after_insert;
CREATE TRIGGER tr_invoice_details_after_insert
AFTER INSERT ON fin_invoice_details
FOR EACH ROW
BEGIN
    CALL sp_insert_invoice_detail_tax (NEW.id);
END;
