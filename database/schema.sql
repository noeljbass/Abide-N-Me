-- Feed My Sheep database foundation (MySQL 8.0+/MariaDB 10.5+)
-- Bible text and canon seed data are intentionally deferred to Iteration 5.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '+00:00';

CREATE TABLE schema_migrations (
    version VARCHAR(50) PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id CHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    avatar_data MEDIUMTEXT NULL,
    email_verified_at TIMESTAMP NULL,
    status ENUM('pending','active','disabled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_public_id (public_id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_settings (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    preferred_translation_id SMALLINT UNSIGNED NULL,
    last_book_id SMALLINT UNSIGNED NULL,
    last_chapter SMALLINT UNSIGNED NULL,
    text_size TINYINT UNSIGNED NOT NULL DEFAULT 100,
    theme ENUM('system','light','dark') NOT NULL DEFAULT 'system',
    reminder_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    reminder_time TIME NULL,
    timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_settings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auth_sessions (
    id CHAR(64) PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    csrf_token_hash CHAR(64) NOT NULL,
    ip_hash CHAR(64) NULL,
    user_agent_hash CHAR(64) NULL,
    last_activity_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_auth_sessions_user (user_id),
    KEY idx_auth_sessions_expiry (expires_at),
    CONSTRAINT fk_auth_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auth_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    purpose ENUM('email_verification','password_reset') NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_auth_tokens_hash (token_hash),
    KEY idx_auth_tokens_user_purpose (user_id, purpose),
    KEY idx_auth_tokens_expiry (expires_at),
    CONSTRAINT fk_auth_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE canons (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(100) NOT NULL,
    book_count SMALLINT UNSIGNED NOT NULL,
    is_default BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_canons_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE books (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(100) NOT NULL,
    testament ENUM('old','new') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_books_code (code),
    UNIQUE KEY uq_books_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE book_names (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    book_id SMALLINT UNSIGNED NOT NULL,
    locale VARCHAR(16) NOT NULL DEFAULT 'en',
    name VARCHAR(100) NOT NULL,
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    UNIQUE KEY uq_book_names_locale_name (locale, name),
    KEY idx_book_names_book (book_id),
    CONSTRAINT fk_book_names_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE canon_books (
    canon_id SMALLINT UNSIGNED NOT NULL,
    book_id SMALLINT UNSIGNED NOT NULL,
    position SMALLINT UNSIGNED NOT NULL,
    chapter_count SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (canon_id, book_id),
    UNIQUE KEY uq_canon_books_position (canon_id, position),
    CONSTRAINT fk_canon_books_canon FOREIGN KEY (canon_id) REFERENCES canons(id) ON DELETE CASCADE,
    CONSTRAINT fk_canon_books_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bible_providers (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    provider_type ENUM('local','api') NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bible_providers_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE translations (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id SMALLINT UNSIGNED NOT NULL,
    canon_id SMALLINT UNSIGNED NOT NULL,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(150) NOT NULL,
    language_code VARCHAR(16) NOT NULL DEFAULT 'en',
    copyright_notice TEXT NULL,
    license_url VARCHAR(500) NULL,
    offline_allowed BOOLEAN NOT NULL DEFAULT FALSE,
    is_active BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_translations_code (code),
    KEY idx_translations_provider (provider_id),
    KEY idx_translations_canon (canon_id),
    CONSTRAINT fk_translations_provider FOREIGN KEY (provider_id) REFERENCES bible_providers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_translations_canon FOREIGN KEY (canon_id) REFERENCES canons(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE user_settings
    ADD CONSTRAINT fk_user_settings_translation FOREIGN KEY (preferred_translation_id) REFERENCES translations(id) ON DELETE SET NULL;

CREATE TABLE translation_books (
    translation_id SMALLINT UNSIGNED NOT NULL,
    book_id SMALLINT UNSIGNED NOT NULL,
    provider_book_id VARCHAR(100) NULL,
    provider_name VARCHAR(150) NULL,
    chapter_count SMALLINT UNSIGNED NOT NULL,
    numbering_metadata JSON NULL,
    PRIMARY KEY (translation_id, book_id),
    UNIQUE KEY uq_translation_provider_book (translation_id, provider_book_id),
    CONSTRAINT fk_translation_books_translation FOREIGN KEY (translation_id) REFERENCES translations(id) ON DELETE CASCADE,
    CONSTRAINT fk_translation_books_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bible_verses (
    translation_id SMALLINT UNSIGNED NOT NULL,
    book_id SMALLINT UNSIGNED NOT NULL,
    chapter SMALLINT UNSIGNED NOT NULL,
    verse SMALLINT UNSIGNED NOT NULL,
    verse_suffix VARCHAR(8) NOT NULL DEFAULT '',
    text MEDIUMTEXT NOT NULL,
    PRIMARY KEY (translation_id, book_id, chapter, verse, verse_suffix),
    KEY idx_bible_verses_reference (book_id, chapter, verse),
    CONSTRAINT fk_bible_verses_translation_book FOREIGN KEY (translation_id, book_id) REFERENCES translation_books(translation_id, book_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audio_providers (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_audio_providers_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audio_versions (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id SMALLINT UNSIGNED NOT NULL,
    translation_id SMALLINT UNSIGNED NULL,
    provider_fileset_id VARCHAR(100) NOT NULL,
    name VARCHAR(150) NOT NULL,
    language_code VARCHAR(16) NOT NULL DEFAULT 'en',
    has_verse_timing BOOLEAN NOT NULL DEFAULT FALSE,
    is_active BOOLEAN NOT NULL DEFAULT FALSE,
    UNIQUE KEY uq_audio_provider_fileset (provider_id, provider_fileset_id),
    KEY idx_audio_versions_translation (translation_id),
    CONSTRAINT fk_audio_versions_provider FOREIGN KEY (provider_id) REFERENCES audio_providers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_audio_versions_translation FOREIGN KEY (translation_id) REFERENCES translations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_book_mappings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_kind ENUM('bible','audio') NOT NULL,
    provider_id SMALLINT UNSIGNED NOT NULL,
    book_id SMALLINT UNSIGNED NOT NULL,
    provider_book_id VARCHAR(100) NOT NULL,
    mapping_metadata JSON NULL,
    UNIQUE KEY uq_provider_book_mapping (provider_kind, provider_id, provider_book_id),
    KEY idx_provider_book_internal (book_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id CHAR(36) NOT NULL,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(500) NULL,
    privacy ENUM('private') NOT NULL DEFAULT 'private',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at TIMESTAMP NULL,
    UNIQUE KEY uq_groups_public_id (public_id),
    KEY idx_groups_owner (owner_user_id),
    CONSTRAINT fk_groups_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE group_members (
    group_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role ENUM('owner','admin','member') NOT NULL DEFAULT 'member',
    status ENUM('active','left','removed') NOT NULL DEFAULT 'active',
    joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (group_id, user_id),
    KEY idx_group_members_user_status (user_id, status),
    KEY idx_group_members_group_role (group_id, role, status),
    CONSTRAINT fk_group_members_group FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    CONSTRAINT fk_group_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE group_invites (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id BIGINT UNSIGNED NOT NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    code_hash CHAR(64) NOT NULL,
    code_hint VARCHAR(8) NULL,
    role ENUM('admin','member') NOT NULL DEFAULT 'member',
    max_uses INT UNSIGNED NULL,
    use_count INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at TIMESTAMP NULL,
    revoked_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_group_invites_hash (code_hash),
    KEY idx_group_invites_group (group_id),
    KEY idx_group_invites_expiry (expires_at),
    CONSTRAINT fk_group_invites_group FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    CONSTRAINT fk_group_invites_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reading_plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id CHAR(36) NOT NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    canon_id SMALLINT UNSIGNED NOT NULL,
    default_translation_id SMALLINT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    builder_mode ENUM('automatic','manual') NOT NULL,
    status ENUM('draft','active','completed','archived') NOT NULL DEFAULT 'draft',
    start_date DATE NOT NULL,
    timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_reading_plans_public_id (public_id),
    KEY idx_reading_plans_creator (created_by_user_id),
    CONSTRAINT fk_reading_plans_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_reading_plans_canon FOREIGN KEY (canon_id) REFERENCES canons(id) ON DELETE RESTRICT,
    CONSTRAINT fk_reading_plans_translation FOREIGN KEY (default_translation_id) REFERENCES translations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE group_plans (
    group_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    assigned_by_user_id BIGINT UNSIGNED NOT NULL,
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (group_id, plan_id),
    KEY idx_group_plans_plan (plan_id),
    CONSTRAINT fk_group_plans_group FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    CONSTRAINT fk_group_plans_plan FOREIGN KEY (plan_id) REFERENCES reading_plans(id) ON DELETE CASCADE,
    CONSTRAINT fk_group_plans_assigner FOREIGN KEY (assigned_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE plan_days (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_id BIGINT UNSIGNED NOT NULL,
    day_number INT UNSIGNED NOT NULL,
    scheduled_date DATE NOT NULL,
    title VARCHAR(150) NULL,
    note TEXT NULL,
    discussion_question TEXT NULL,
    UNIQUE KEY uq_plan_days_number (plan_id, day_number),
    UNIQUE KEY uq_plan_days_date (plan_id, scheduled_date),
    KEY idx_plan_days_schedule (scheduled_date),
    CONSTRAINT fk_plan_days_plan FOREIGN KEY (plan_id) REFERENCES reading_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE plan_passages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_day_id BIGINT UNSIGNED NOT NULL,
    position SMALLINT UNSIGNED NOT NULL,
    start_book_id SMALLINT UNSIGNED NOT NULL,
    start_chapter SMALLINT UNSIGNED NOT NULL,
    start_verse SMALLINT UNSIGNED NULL,
    end_book_id SMALLINT UNSIGNED NOT NULL,
    end_chapter SMALLINT UNSIGNED NOT NULL,
    end_verse SMALLINT UNSIGNED NULL,
    display_reference VARCHAR(150) NOT NULL,
    estimated_read_seconds INT UNSIGNED NULL,
    estimated_listen_seconds INT UNSIGNED NULL,
    UNIQUE KEY uq_plan_passages_position (plan_day_id, position),
    KEY idx_plan_passages_start (start_book_id, start_chapter, start_verse),
    KEY idx_plan_passages_end (end_book_id, end_chapter, end_verse),
    CONSTRAINT fk_plan_passages_day FOREIGN KEY (plan_day_id) REFERENCES plan_days(id) ON DELETE CASCADE,
    CONSTRAINT fk_plan_passages_start_book FOREIGN KEY (start_book_id) REFERENCES books(id) ON DELETE RESTRICT,
    CONSTRAINT fk_plan_passages_end_book FOREIGN KEY (end_book_id) REFERENCES books(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_passage_progress (
    user_id BIGINT UNSIGNED NOT NULL,
    passage_id BIGINT UNSIGNED NOT NULL,
    progress_percent DECIMAL(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
    last_book_id SMALLINT UNSIGNED NULL,
    last_chapter SMALLINT UNSIGNED NULL,
    last_verse SMALLINT UNSIGNED NULL,
    completed_at TIMESTAMP NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, passage_id),
    KEY idx_passage_progress_passage_complete (passage_id, completed_at),
    CONSTRAINT fk_passage_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_passage_progress_passage FOREIGN KEY (passage_id) REFERENCES plan_passages(id) ON DELETE CASCADE,
    CONSTRAINT fk_passage_progress_book FOREIGN KEY (last_book_id) REFERENCES books(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audio_progress (
    user_id BIGINT UNSIGNED NOT NULL,
    passage_id BIGINT UNSIGNED NOT NULL,
    audio_version_id SMALLINT UNSIGNED NOT NULL,
    book_id SMALLINT UNSIGNED NOT NULL,
    chapter SMALLINT UNSIGNED NOT NULL,
    verse SMALLINT UNSIGNED NULL,
    audio_position_seconds DECIMAL(10,3) UNSIGNED NOT NULL DEFAULT 0,
    listened_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, passage_id),
    KEY idx_audio_progress_resume (user_id, updated_at),
    CONSTRAINT fk_audio_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_audio_progress_passage FOREIGN KEY (passage_id) REFERENCES plan_passages(id) ON DELETE CASCADE,
    CONSTRAINT fk_audio_progress_version FOREIGN KEY (audio_version_id) REFERENCES audio_versions(id) ON DELETE RESTRICT,
    CONSTRAINT fk_audio_progress_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE private_notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    passage_id BIGINT UNSIGNED NULL,
    book_id SMALLINT UNSIGNED NOT NULL,
    start_chapter SMALLINT UNSIGNED NOT NULL,
    start_verse SMALLINT UNSIGNED NULL,
    end_chapter SMALLINT UNSIGNED NULL,
    end_verse SMALLINT UNSIGNED NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_private_notes_user_reference (user_id, book_id, start_chapter),
    KEY idx_private_notes_passage (passage_id),
    CONSTRAINT fk_private_notes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_private_notes_passage FOREIGN KEY (passage_id) REFERENCES plan_passages(id) ON DELETE SET NULL,
    CONSTRAINT fk_private_notes_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE group_notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id BIGINT UNSIGNED NOT NULL,
    author_user_id BIGINT UNSIGNED NOT NULL,
    plan_day_id BIGINT UNSIGNED NULL,
    passage_id BIGINT UNSIGNED NULL,
    book_id SMALLINT UNSIGNED NULL,
    chapter SMALLINT UNSIGNED NULL,
    start_verse SMALLINT UNSIGNED NULL,
    end_verse SMALLINT UNSIGNED NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    KEY idx_group_notes_group_created (group_id, created_at),
    KEY idx_group_notes_reference (book_id, chapter, start_verse),
    KEY idx_group_notes_author (author_user_id),
    CONSTRAINT fk_group_notes_group FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    CONSTRAINT fk_group_notes_author FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_group_notes_day FOREIGN KEY (plan_day_id) REFERENCES plan_days(id) ON DELETE CASCADE,
    CONSTRAINT fk_group_notes_passage FOREIGN KEY (passage_id) REFERENCES plan_passages(id) ON DELETE CASCADE,
    CONSTRAINT fk_group_notes_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE group_note_replies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_note_id BIGINT UNSIGNED NOT NULL,
    author_user_id BIGINT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    KEY idx_group_note_replies_note_created (group_note_id, created_at),
    KEY idx_group_note_replies_author (author_user_id),
    CONSTRAINT fk_group_note_replies_note FOREIGN KEY (group_note_id) REFERENCES group_notes(id) ON DELETE CASCADE,
    CONSTRAINT fk_group_note_replies_author FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE highlights (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    translation_id SMALLINT UNSIGNED NOT NULL,
    book_id SMALLINT UNSIGNED NOT NULL,
    chapter SMALLINT UNSIGNED NOT NULL,
    start_verse SMALLINT UNSIGNED NOT NULL,
    end_verse SMALLINT UNSIGNED NOT NULL,
    color VARCHAR(20) NOT NULL DEFAULT 'gold',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_highlights_range (user_id, translation_id, book_id, chapter, start_verse, end_verse),
    CONSTRAINT fk_highlights_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_highlights_translation FOREIGN KEY (translation_id) REFERENCES translations(id) ON DELETE CASCADE,
    CONSTRAINT fk_highlights_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bookmarks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    translation_id SMALLINT UNSIGNED NOT NULL,
    book_id SMALLINT UNSIGNED NOT NULL,
    chapter SMALLINT UNSIGNED NOT NULL,
    verse SMALLINT UNSIGNED NULL,
    label VARCHAR(100) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bookmarks_reference (user_id, translation_id, book_id, chapter, verse),
    CONSTRAINT fk_bookmarks_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_bookmarks_translation FOREIGN KEY (translation_id) REFERENCES translations(id) ON DELETE CASCADE,
    CONSTRAINT fk_bookmarks_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
    action VARCHAR(50) NOT NULL,
    subject_hash CHAR(64) NOT NULL,
    window_started_at TIMESTAMP NOT NULL,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    blocked_until TIMESTAMP NULL,
    PRIMARY KEY (action, subject_hash),
    KEY idx_rate_limits_cleanup (window_started_at, blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE api_cache (
    cache_key CHAR(64) PRIMARY KEY,
    provider VARCHAR(50) NOT NULL,
    content_type VARCHAR(100) NOT NULL DEFAULT 'application/json',
    payload MEDIUMBLOB NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_api_cache_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE push_subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    endpoint_hash CHAR(64) NOT NULL,
    endpoint TEXT NOT NULL,
    public_key VARCHAR(255) NOT NULL,
    auth_token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_push_endpoint_hash (endpoint_hash),
    KEY idx_push_subscriptions_user (user_id),
    CONSTRAINT fk_push_subscriptions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (version) VALUES ('001_database_foundation');
