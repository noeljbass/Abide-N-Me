-- Stable, non-sequential identifiers for passage-facing APIs.
START TRANSACTION;
ALTER TABLE plan_passages ADD COLUMN public_id CHAR(36) NULL AFTER id;
UPDATE plan_passages SET public_id = UUID() WHERE public_id IS NULL;
ALTER TABLE plan_passages MODIFY public_id CHAR(36) NOT NULL;
ALTER TABLE plan_passages ADD UNIQUE KEY uq_plan_passages_public_id (public_id);
INSERT INTO schema_migrations (version) VALUES ('004_passage_public_ids')
ON DUPLICATE KEY UPDATE version = VALUES(version);
COMMIT;
