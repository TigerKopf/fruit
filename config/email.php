<?php
// config/email.php

// Environment variables for email configuration
define('MAIL_TO_ADDRESS', getenv('MAIL_TO_ADDRESS') ?: 'your_recipient@example.com');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Kontaktformular');
define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@yourdomain.com');
// Für PHPMailer-Integration würden hier weitere SMTP-Konfigurationen stehen:
// define('SMTP_HOST', getenv('SMTP_HOST'));
// define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
// define('SMTP_USERNAME', getenv('SMTP_USERNAME'));
// define('SMTP_PASSWORD', getenv('SMTP_PASSWORD'));
// define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: 'tls'); // or 'ssl' or ''
?>
