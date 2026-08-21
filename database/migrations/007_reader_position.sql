ALTER TABLE user_settings
    ADD COLUMN last_book_id SMALLINT UNSIGNED NULL AFTER preferred_translation_id,
    ADD COLUMN last_chapter SMALLINT UNSIGNED NULL AFTER last_book_id,
    ADD CONSTRAINT fk_user_settings_last_book FOREIGN KEY (last_book_id) REFERENCES books(id) ON DELETE SET NULL;

INSERT INTO schema_migrations (version) VALUES ('007_reader_position');
