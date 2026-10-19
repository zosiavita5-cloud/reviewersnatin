
CREATE DATABASE IF NOT EXISTS greenreview_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE greenreview_db;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reviewers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    subject VARCHAR(100) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NULL,
    status ENUM('pending', 'approved', 'rejected')
        NOT NULL DEFAULT 'approved',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_public_reviewers (status, uploaded_at),
    INDEX idx_reviewer_user (user_id)
) ENGINE=InnoDB;

ALTER TABLE reviewers
    ADD COLUMN IF NOT EXISTS original_name VARCHAR(255) NULL;
