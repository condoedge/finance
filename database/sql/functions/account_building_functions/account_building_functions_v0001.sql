-- Dynamic Account Building Functions for Condoedge Finance Package
-- These functions handle dynamic segment-based account construction

DELIMITER $$

DROP FUNCTION IF EXISTS build_account_descriptor$$

-- Function to build human-readable account descriptor
CREATE FUNCTION build_account_descriptor(p_account_id INT)
RETURNS TEXT
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_descriptor TEXT DEFAULT '';
    DECLARE v_value_description VARCHAR(255);
    DECLARE v_segment_value VARCHAR(50);
    DECLARE done INT DEFAULT FALSE;
    
    -- Cursor to iterate through segments
    DECLARE segment_cursor CURSOR FOR
        SELECT 
            COALESCE(sv.segment_value) as display_value
        FROM fin_account_segment_assignments asa
        JOIN fin_segment_values sv ON asa.segment_value_id = sv.id
        JOIN fin_account_segments seg ON sv.segment_definition_id = seg.id
        WHERE asa.account_id = p_account_id
        ORDER BY seg.segment_position;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN segment_cursor;
    
    read_loop: LOOP
        FETCH segment_cursor INTO v_value_description;
        
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        IF v_descriptor != '' THEN
            SET v_descriptor = CONCAT(v_descriptor, ' - ');
        END IF;
        
        SET v_descriptor = CONCAT(v_descriptor, v_value_description);
    END LOOP;
    
    CLOSE segment_cursor;
    
    RETURN v_descriptor;
END$$

DROP FUNCTION IF EXISTS get_account_segment_value$$

-- Function to get segment value for specific position
CREATE FUNCTION get_account_segment_value(p_account_id INT, p_segment_position INT)
RETURNS VARCHAR(50)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_segment_value VARCHAR(50) DEFAULT NULL;
    
    SELECT sv.segment_value INTO v_segment_value
    FROM fin_account_segment_assignments asa
    JOIN fin_segment_values sv ON asa.segment_value_id = sv.id
    JOIN fin_account_segments seg ON sv.segment_definition_id = seg.id
    WHERE asa.account_id = p_account_id
    AND seg.segment_position = p_segment_position
    LIMIT 1;
    
    RETURN v_segment_value;
END$$

DELIMITER ;