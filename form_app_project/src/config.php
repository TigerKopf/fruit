<?php
// Datenbankkonfiguration
define('DB_HOST', 'db'); // 'db' ist der Hostname, wenn Docker Compose verwendet wird
define('DB_USER', 'mariadb');
define('DB_PASS', 'I8Q8WHc5EViIcrRpSvToHPCfTqQq78pfppjzgmmA0BTSpDSW0Dv3CrhGcVZccfBe');
define('DB_NAME', 'default');

// E-Mail-Konfiguration
define('EMAIL_RECIPIENT', 'your_recipient_email@example.com'); // E-Mail-Adresse, an die das Formular gesendet werden soll
define('EMAIL_SENDER_NAME', 'Formular Absender');
define('EMAIL_SENDER_EMAIL', 'your_sender_email@example.com'); // Muss mit der in msmtprc konfigurierten Absender-E-Mail übereinstimmen
define('EMAIL_SUBJECT_PREFIX', '[Formular Anfrage]');

// Optional: Zeitzone setzen
date_default_timezone_set('Europe/Berlin');

// Fehlerberichterstattung (nur für Entwicklung, in Produktion deaktivieren oder einschränken)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>
