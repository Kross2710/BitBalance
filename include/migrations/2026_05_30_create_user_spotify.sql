-- include/migrations/2026_05_30_create_user_spotify.sql
-- Migration to support Spotify integration tokens in BitBalance

CREATE TABLE IF NOT EXISTS user_spotify (
    user_id INT PRIMARY KEY,
    access_token TEXT NOT NULL,
    refresh_token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES user(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
