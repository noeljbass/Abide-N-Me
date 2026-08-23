-- Give every existing and future group one permanent, shareable four-character code.
-- The plaintext is retained because the application must display the code again.
ALTER TABLE groups
    ADD COLUMN join_code CHAR(4) NULL AFTER description,
    ADD COLUMN join_code_hash CHAR(64) NULL AFTER join_code;

DELIMITER //
CREATE PROCEDURE backfill_group_join_codes()
BEGIN
    DECLARE finished INTEGER DEFAULT 0;
    DECLARE group_to_update BIGINT UNSIGNED;
    DECLARE candidate CHAR(4);
    DECLARE alphabet CHAR(32) DEFAULT 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    DECLARE groups_without_codes CURSOR FOR SELECT id FROM groups WHERE join_code IS NULL ORDER BY id;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET finished = 1;

    OPEN groups_without_codes;
    group_loop: LOOP
        FETCH groups_without_codes INTO group_to_update;
        IF finished = 1 THEN LEAVE group_loop; END IF;
        code_loop: LOOP
            SET candidate = CONCAT(
                SUBSTRING(alphabet, FLOOR(RAND() * 32) + 1, 1),
                SUBSTRING(alphabet, FLOOR(RAND() * 32) + 1, 1),
                SUBSTRING(alphabet, FLOOR(RAND() * 32) + 1, 1),
                SUBSTRING(alphabet, FLOOR(RAND() * 32) + 1, 1)
            );
            IF NOT EXISTS (SELECT 1 FROM groups WHERE join_code = candidate) THEN
                UPDATE groups
                SET join_code = candidate, join_code_hash = SHA2(candidate, 256)
                WHERE id = group_to_update;
                LEAVE code_loop;
            END IF;
        END LOOP;
    END LOOP;
    CLOSE groups_without_codes;
END//
DELIMITER ;

CALL backfill_group_join_codes();
DROP PROCEDURE backfill_group_join_codes;

ALTER TABLE groups
    MODIFY join_code CHAR(4) NOT NULL,
    MODIFY join_code_hash CHAR(64) NOT NULL,
    ADD UNIQUE KEY uq_groups_join_code_hash (join_code_hash);

INSERT INTO schema_migrations (version) VALUES ('010_permanent_group_codes')
ON DUPLICATE KEY UPDATE version = VALUES(version);
