<?php
// include/db.php

/**
 * Datenbank-Verbindungslogik.
 *
 * Bindet die sensible Konfiguration ein und stellt eine Datenbankverbindung her.
 */


// Sensible Konfiguration einbinden
// ROOT_PATH muss in config/config.php definiert sein, um den Pfad korrekt aufzulösen
require_once ROOT_PATH . 'config/sensitive_config.php';

// Globale Variable für die Datenbankverbindung
// Alternativ könnte man eine Funktion erstellen, die die Verbindung zurückgibt
global $pdo;

try {
    // 1. Diagnose: Verfügbare PDO-Treiber prüfen
    $availableDrivers = PDO::getAvailableDrivers();
    if (!in_array('mariadb', $availableDrivers) && !in_array('mysql', $availableDrivers)) {
        $driverErrorMsg = "Der 'mariadb' oder 'mysql' PDO-Treiber wurde nicht gefunden. Verfügbare Treiber: " . implode(', ', $availableDrivers);
        error_log("Database Connection Error: " . $driverErrorMsg);
        die("Datenbankverbindung fehlgeschlagen: " . $driverErrorMsg);
    }
    
    // 2. DSN-Präfix anpassen, falls 'mariadb' nicht direkt unterstützt wird, aber 'mysql' vorhanden ist
    $dbDriver = 'mariadb';
    if (!in_array('mariadb', $availableDrivers) && in_array('mysql', $availableDrivers)) {
        $dbDriver = 'mysql'; // Fallback auf 'mysql' als DSN-Präfix, da MariaDB oft den 'mysql'-Treiber verwendet
        error_log("Info: 'mariadb' PDO-Treiber nicht direkt gefunden, verwende 'mysql' als DSN-Präfix.");
    }

    $dsn = "$dbDriver:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Im Falle eines Fehlers die Fehlermeldung ausgeben
    // In einer Produktionsumgebung sollte man diese Fehlermeldung loggen und dem Benutzer eine generische Fehlermeldung zeigen.
    error_log("Database Connection Error: " . $e->getMessage());
    die("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
}

// Beispiel einer Funktion, die die PDO-Instanz zurückgibt (optional, wenn $pdo global ist)
function getDbConnection(): PDO {
    global $pdo;
    return $pdo;
}
?>