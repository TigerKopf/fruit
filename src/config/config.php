<?php

/**
 * Globale Konfigurationseinstellungen
 */

// Absoluter Pfad zum Wurzelverzeichnis der Anwendung
define('ROOT_PATH', __DIR__ . '/../');

// Name der Anwendung
define('APP_NAME', 'Früchte aus Portugal');

// Basis-URL (optional, kann nützlich sein für Links)
// Beispiel: define('BASE_URL', 'https://domain.de/');

// Datenbank-Konfiguration (Beispiel für MySQLi)
/*
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
define('DB_NAME', 'your_database');

function connect_db() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Verbindung zur Datenbank fehlgeschlagen: " . $conn->connect_error);
    }
    return $conn;
}
*/

?>
