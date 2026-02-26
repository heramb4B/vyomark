-- Run this once to create the site_visits table
CREATE TABLE IF NOT EXISTS site_visits (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    ip          VARCHAR(45)  NOT NULL,
    user_agent  TEXT,
    page        VARCHAR(255) DEFAULT '/',
    visited_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_visited_at (visited_at),
    INDEX idx_ip (ip)
);