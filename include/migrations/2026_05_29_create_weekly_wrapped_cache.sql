CREATE TABLE IF NOT EXISTS weekly_wrapped_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    week_year VARCHAR(10) NOT NULL, -- e.g., '22-2026' (week 22, year 2026)
    lang VARCHAR(5) NOT NULL,       -- 'en' or 'vi'
    generated_json TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_week_lang (user_id, week_year, lang)
);
