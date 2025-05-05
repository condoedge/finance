DELIMITER $$

DROP FUNCTION IF EXISTS get_amount_using_sign_multiplier$$
CREATE FUNCTION get_amount_using_sign_multiplier(amount DECIMAL(19, 5), sign_multiplier INT) RETURNS DECIMAL(19, 5)
BEGIN
    IF amount IS NULL THEN
        RETURN 0.00;
    END IF;

    IF sign_multiplier IS NULL THEN
        RETURN amount;
    END IF;

    IF amount / sign_multiplier < 0 THEN
        RETURN amount * -1;
    END IF;

    RETURN amount;
END$$

DELIMITER ;