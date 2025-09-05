-- Stellen Sie sicher, dass die Datenbank 'form_db' existiert oder erstellen Sie diese manuell
-- Beispiel für MySQL/MariaDB:
-- CREATE DATABASE IF NOT EXISTS form_db;
-- USE form_db;

CREATE TABLE IF NOT EXISTS form_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    submission_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
