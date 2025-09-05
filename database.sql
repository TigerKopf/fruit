-- Erstellt die Datenbank, falls sie noch nicht existiert
CREATE DATABASE IF NOT EXISTS `form_db`;

-- Wählt die erstellte Datenbank aus
USE `form_db`;

-- Erstellt die Tabelle für Formularübermittlungen
CREATE TABLE IF NOT EXISTS `form_submissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(255),
    `message` TEXT NOT NULL,
    `submission_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
