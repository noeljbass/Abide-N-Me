-- Replace email-based identity with unique, anonymous usernames.
-- Existing accounts receive a predictable temporary username for migration.
ALTER TABLE users ADD COLUMN username VARCHAR(32) NULL AFTER name;
UPDATE users SET username = CONCAT('member', id) WHERE username IS NULL;
ALTER TABLE users
    MODIFY username VARCHAR(32) NOT NULL,
    ADD UNIQUE KEY uq_users_username (username),
    DROP INDEX uq_users_email,
    DROP COLUMN email,
    DROP COLUMN email_verified_at;

DROP TABLE IF EXISTS auth_tokens;

INSERT INTO schema_migrations (version) VALUES ('008_username_accounts');
