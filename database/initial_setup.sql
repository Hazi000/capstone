-- Create database if not exists
CREATE DATABASE IF NOT EXISTS barangay_management;
USE barangay_management;

-- Create residents table
CREATE TABLE IF NOT EXISTS residents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(100) NOT NULL,
    middle_initial VARCHAR(1),
    last_name VARCHAR(100) NOT NULL,
    full_name VARCHAR(255),
    age INT NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    email VARCHAR(100) UNIQUE,
    zone ENUM('Zone 1','Zone 2','Zone 3','Zone 4','Zone 5','Zone 6') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    photo_path VARCHAR(255),
    face_descriptor TEXT,
    total_volunteer_hours DECIMAL(6,1) DEFAULT 0.0,
    total_volunteer_events INT DEFAULT 0,
    last_volunteer_date DATE,
    volunteer_status ENUM('inactive','active','outstanding') DEFAULT 'inactive'
);

-- Create users table (for admin/secretary accounts)
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('captain','secretary','super_admin') NOT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create resident_accounts table
CREATE TABLE IF NOT EXISTS resident_accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    resident_id INT NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    is_verified BOOLEAN DEFAULT 0,
    verification_token VARCHAR(100),
    last_login DATETIME,
    login_attempts INT DEFAULT 0,
    account_locked BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE
);

-- Create complaints table
CREATE TABLE IF NOT EXISTS complaints (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nature_of_complaint VARCHAR(255),
    description TEXT NOT NULL,
    priority ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    status ENUM('pending','in-progress','resolved','closed') NOT NULL DEFAULT 'pending',
    resident_id INT,
    complainant_name VARCHAR(255),
    complainant_contact VARCHAR(20),
    defendant_resident_id INT,
    defendant_name VARCHAR(255),
    defendant_contact VARCHAR(20),
    resolution TEXT,
    mediation_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE SET NULL,
    FOREIGN KEY (defendant_resident_id) REFERENCES residents(id)
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create announcements table
CREATE TABLE IF NOT EXISTS announcements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    priority ENUM('low','medium','high') NOT NULL DEFAULT 'low',
    announcement_type VARCHAR(20) DEFAULT 'general',
    status ENUM('active','inactive','expired') NOT NULL DEFAULT 'active',
    event_date DATE,
    event_time TIME,
    location VARCHAR(500),
    expiry_date DATE,
    needs_volunteers BOOLEAN DEFAULT 0,
    max_volunteers INT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Create community_volunteers table
CREATE TABLE IF NOT EXISTS community_volunteers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    resident_id INT NOT NULL,
    announcement_id INT NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    rejection_reason TEXT,
    attendance_status ENUM('pending','attended','absent') DEFAULT 'pending',
    hours_served DECIMAL(4,1) DEFAULT 0.0,
    approved_by INT,
    approved_at DATETIME,
    attendance_marked_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (resident_id) REFERENCES residents(id),
    FOREIGN KEY (announcement_id) REFERENCES announcements(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- Create certificates table
CREATE TABLE IF NOT EXISTS certificates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    resident_name VARCHAR(255),
    age INT,
    purpose TEXT,
    or_number VARCHAR(50),
    amount_paid VARCHAR(50),
    issued_date DATETIME,
    issued_by INT,
    FOREIGN KEY (issued_by) REFERENCES users(id)
);

-- Create certificate_types table
CREATE TABLE IF NOT EXISTS certificate_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    processing_days INT DEFAULT 3,
    requirements TEXT,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create certificate_requests table
CREATE TABLE IF NOT EXISTS certificate_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    resident_id INT NOT NULL,
    certificate_type VARCHAR(100) NOT NULL,
    purpose TEXT NOT NULL,
    additional_info TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_date TIMESTAMP NULL,
    processed_by INT,
    rejection_reason TEXT,
    claim_date TIMESTAMP NULL,
    or_number VARCHAR(50),
    amount_paid DECIMAL(10,2),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Add initial super admin account (password: admin123)
INSERT INTO users (full_name, email, password, role) VALUES 
('Super Admin', 'admin@example.com', '$2y$10$rzmGbrr2O7emlrI9uVBSBelQLkooWMp5zlOi711FbVVr3Gyn7LgQG', 'super_admin');

-- Add some initial certificate types
INSERT INTO certificate_types (name, description, fee, requirements) VALUES
('Barangay Clearance', 'General purpose clearance for various transactions', 50.00, 'Valid ID, Proof of Residency'),
('Certificate of Indigency', 'For indigent residents requiring assistance', 0.00, 'Valid ID, Proof of Income'),
('Certificate of Residency', 'Proof of residence in the barangay', 30.00, 'Valid ID, Proof of Address');

-- Create volunteer stats update trigger
DELIMITER $$
CREATE TRIGGER update_resident_volunteer_stats 
AFTER UPDATE ON community_volunteers
FOR EACH ROW
BEGIN
    IF NEW.attendance_status = 'attended' AND OLD.attendance_status != 'attended' THEN
        UPDATE residents 
        SET total_volunteer_hours = total_volunteer_hours + NEW.hours_served,
            total_volunteer_events = total_volunteer_events + 1,
            last_volunteer_date = CURDATE(),
            volunteer_status = CASE 
                WHEN total_volunteer_hours + NEW.hours_served >= 100 THEN 'outstanding'
                WHEN total_volunteer_hours + NEW.hours_served >= 20 THEN 'active'
                ELSE 'inactive'
            END
        WHERE id = NEW.resident_id;
    END IF;
END$$
DELIMITER ;
