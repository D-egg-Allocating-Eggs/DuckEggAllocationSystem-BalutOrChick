-- =====================================================
-- Complete Database Schema for Duck Egg System
-- with Email Verification Support
-- =====================================================

START TRANSACTION;

-- =====================================================
-- 1. USERS TABLE (Updated with email verification)
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    user_role ENUM('admin', 'manager', 'user') DEFAULT 'user',
    is_verified BOOLEAN DEFAULT 0,
    verification_token VARCHAR(64) NULL,
    email_verification_expires DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_verification_token (verification_token),
    INDEX idx_user_role (user_role)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- =====================================================
-- 2. EGG TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS egg (
    egg_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_egg INT NOT NULL,
    status ENUM('incubating', 'complete') NOT NULL,
    date_started_incubation TIMESTAMP NOT NULL,
    balut_count INT DEFAULT 0,
    failed_count INT DEFAULT 0,
    chick_count INT DEFAULT 0,
    batch_number INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_egg_user (user_id),
    INDEX idx_status (status),
    INDEX idx_batch_number (batch_number),
    CONSTRAINT fk_egg_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- =====================================================
-- 3. EGG DAILY LOGS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS egg_daily_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    egg_id INT NOT NULL,
    day_number INT NOT NULL,
    failed_count INT DEFAULT 0,
    balut_count INT DEFAULT 0,
    chick_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_egg_id (egg_id),
    INDEX idx_day_number (day_number),
    CONSTRAINT fk_daily_egg FOREIGN KEY (egg_id) REFERENCES egg(egg_id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- =====================================================
-- 4. USER ACTIVITY LOGS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS user_activity_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    log_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_log_date (log_date),
    CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

COMMIT;





INSERT INTO users (username, email, password, user_role, is_verified, verification_token, email_verification_expires, created_at) VALUES
-- Admin User (Verified)
(
    'admin', 
    'admin@eggflow.com', 
    '$2y$10$TaqeLP1U2pWSIDa/hftZoez.G/cVtaJ9MoeKGRB6pSk91C5p3/c8.', 
    'admin', 
    1, 
    NULL, 
    NULL, 
    NOW()
),

-- Manager User (Verified)
(
    'manager', 
    'manager@eggflow.com', 
    '$2y$10$TaqeLP1U2pWSIDa/hftZoez.G/cVtaJ9MoeKGRB6pSk91C5p3/c8.', 
    'manager', 
    1, 
    NULL, 
    NULL, 
    NOW()
),

-- Regular User - John (Verified)
(
    'john_doe', 
    'john@example.com', 
    '$2y$10$TaqeLP1U2pWSIDa/hftZoez.G/cVtaJ9MoeKGRB6pSk91C5p3/c8.', 
    'user', 
    1, 
    NULL, 
    NULL, 
    NOW()
),

-- Regular User - Jane (Not Verified - for testing verification flow)
(
    'jane_smith', 
    'jane@example.com', 
    '$2y$10$TaqeLP1U2pWSIDa/hftZoez.G/cVtaJ9MoeKGRB6pSk91C5p3/c8.', 
    'user', 
    0, 
    'test_verification_token_' || MD5(RAND()), 
    DATE_ADD(NOW(), INTERVAL 24 HOUR), 
    NOW()
);
