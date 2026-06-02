-- include/migrations/2026_06_02_create_user_identity.sql
-- Social login: maps an external identity provider account (Google today,
-- Apple/Facebook later) to a BitBalance user. One row per linked provider
-- account. A BitBalance user may have several rows (one per provider) plus
-- their local email/password login.
--
-- Linking rule: a provider account is matched first by (provider, provider_uid),
-- then by verified email to an existing local account. See
-- include/handlers/google_oauth.php.

CREATE TABLE IF NOT EXISTS user_identity (
    identity_id  INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    provider     VARCHAR(20)  NOT NULL,           -- 'google', later 'apple', etc.
    provider_uid VARCHAR(255) NOT NULL,           -- stable provider id (Google 'sub')
    email        VARCHAR(255) DEFAULT NULL,        -- provider email at link time (reference only)
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_provider_uid (provider, provider_uid),
    KEY idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES user(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
