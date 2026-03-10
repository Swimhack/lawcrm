-- Law CRM Database Schema
-- Run against: admin_law_firm_crm

-- Users table (replaces hardcoded password)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Leads table (preserves existing data if table exists)
CREATE TABLE IF NOT EXISTS leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20) DEFAULT '',
    practice_area VARCHAR(100) NOT NULL,
    status ENUM('New', 'In Progress', 'Closed') DEFAULT 'New',
    score TINYINT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_practice_area (practice_area),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Default admin user (password: changeme123)
-- CHANGE THIS PASSWORD IMMEDIATELY after first login
INSERT IGNORE INTO users (name, email, password_hash) VALUES (
    'Admin',
    'admin@law-crm.com',
    '$2y$10$YourHashWillBeGeneratedBySetupScript'
);
