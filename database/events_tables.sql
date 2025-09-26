-- Events table
CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_start_date DATE NOT NULL,
    event_end_date DATE NOT NULL,
    event_time TIME,
    location VARCHAR(255),
    needs_volunteers BOOLEAN DEFAULT FALSE,
    max_volunteers INT DEFAULT 0,
    created_by INT,
    status ENUM('upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'upcoming',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    CHECK (event_end_date >= event_start_date)
);

-- Volunteer requests table
CREATE TABLE volunteer_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    role VARCHAR(100),
    description TEXT,
    required_volunteers INT DEFAULT 1,
    filled_positions INT DEFAULT 0,
    status ENUM('open', 'filled', 'closed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

-- Volunteer registrations table
CREATE TABLE volunteer_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    request_id INT NOT NULL,
    resident_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (request_id) REFERENCES volunteer_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (resident_id) REFERENCES residents(id),
    UNIQUE KEY unique_registration (event_id, request_id, resident_id)
);

-- Add rejection_reason column to volunteer_registrations table
ALTER TABLE volunteer_registrations 
ADD COLUMN rejection_reason TEXT NULL AFTER status;

-- Indexes for better performance
CREATE INDEX idx_event_dates ON events(event_start_date, event_end_date);
CREATE INDEX idx_event_status ON events(status);
CREATE INDEX idx_volunteer_request_status ON volunteer_requests(status);
CREATE INDEX idx_volunteer_registration_status ON volunteer_registrations(status);

-- Insert volunteer requests for existing events that need volunteers
INSERT INTO volunteer_requests (event_id, required_volunteers, status)
SELECT id, max_volunteers, 'open'
FROM events 
WHERE needs_volunteers = 1 
AND id NOT IN (SELECT event_id FROM volunteer_requests);

-- Update trigger to better handle volunteer requests
DELIMITER //
DROP TRIGGER IF EXISTS create_volunteer_request//
CREATE TRIGGER create_volunteer_request AFTER INSERT ON events
FOR EACH ROW
BEGIN
    IF NEW.needs_volunteers = 1 AND NEW.max_volunteers > 0 THEN
        INSERT INTO volunteer_requests (event_id, required_volunteers, status)
        VALUES (NEW.id, NEW.max_volunteers, 'open');
    END IF;
END//
DELIMITER ;
