-- Import provenance and reader annotation support for verified local Bible packages.
START TRANSACTION;
CREATE TABLE IF NOT EXISTS bible_imports (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 translation_id SMALLINT UNSIGNED NOT NULL,
 source_identifier VARCHAR(80) NOT NULL,
 package_filename VARCHAR(255) NOT NULL,
 package_sha256 CHAR(64) NOT NULL,
 source_url VARCHAR(500) NOT NULL,
 imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 book_count SMALLINT UNSIGNED NOT NULL,
 chapter_count INT UNSIGNED NOT NULL,
 verse_count INT UNSIGNED NOT NULL,
 validation_report JSON NOT NULL,
 UNIQUE KEY uq_bible_import_package (translation_id, package_sha256),
 CONSTRAINT fk_bible_import_translation FOREIGN KEY (translation_id) REFERENCES translations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO schema_migrations (version) VALUES ('003_bible_text_import') ON DUPLICATE KEY UPDATE version=VALUES(version);
COMMIT;
