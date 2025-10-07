-- 1️⃣ Create the database
CREATE DATABASE IF NOT EXISTS nagarsevak_appointments CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 2️⃣ Use the database
USE nagarsevak_appointments;

-- 3️⃣ Create the appointments table
CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    profession VARCHAR(100) NOT NULL,
    reason TEXT NOT NULL,
    contact_number VARCHAR(15) NOT NULL,
    urgent_contact BOOLEAN DEFAULT 0,
    appointment_date DATETIME NOT NULL,
    booking_reference VARCHAR(50) NOT NULL UNIQUE,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4️⃣ Optional: Add an admin table (for future login panel)
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5️⃣ Optional: Create a demo admin user (username: admin / password: admin123)
INSERT INTO admins (username, password_hash)
VALUES ('admin', SHA2('admin123', 256));
