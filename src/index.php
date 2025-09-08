<?php

/**
 * Kern-Datei der Anwendung
 * Diese Datei verarbeitet alle Anfragen dank .htaccess
 */

// --- PHASE 1: ABSOLUT NOTWENDIGE KONFIGURATION UND ASSET-HANDLING ---
// Konfigurationsdatei einbinden (Bleibt hier, da sie grundlegende Konstanten wie ROOT_PATH definiert)
require_once __DIR__ . '/config/config.php';

// Angeforderte URI extrahieren
$request_uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$segments = explode('/', $request_uri);
$page = !empty($segments[0]) ? $segments[0] : 'home';

// Asset Handler Funktion definieren (NICHT die ganze Loader-Datei laden!)
// Wir definieren die Funktion hier direkt oder laden nur die asset_handler.php
// ohne die anderen Loader, um Performance zu sparen.
// Die asset_handler.php muss so umgeschrieben werden, dass sie keine anderen Includes hat.
require_once ROOT_PATH . 'include/asset_handler.php';

// Prüfen, ob eine Asset-Anfrage vorliegt und diese behandeln
// Wenn handleAssetRequest() ein Asset findet und ausliefert, beendet es das Skript.
handleAssetRequest($page);

// --- PHASE 2: LADEN ALLER WEITEREN INCLUDES NUR FÜR NICHT-ASSET-ANFRAGEN ---
// Wenn das Skript hier ankommt, ist es KEIN Asset. Jetzt können wir alle anderen Loader laden.
require_once ROOT_PATH . 'include/loader.php'; // Lädt startup.php, db.php, email.php, helpers.php etc.
require_once ROOT_PATH . 'vendor/autoload.php'; // Composer Autoload für PHPMailer etc.

// Session starten (wichtig für den Warenkorb und Admin-Login)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


// --- PHASE 3: ROUTING FÜR API, ADMIN UND REGULÄRE SEITEN ---

// API ROUTING
if ($page === 'api' && isset($segments[1])) {
    $api_file = ROOT_PATH . 'api/' . $segments[1];
    if (file_exists($api_file)) {
        require_once $api_file;
        exit();
    } else {
        header("HTTP/1.0 404 Not Found");
        require_once ROOT_PATH . 'modules/404.php';
        exit();
    }
}

// ADMIN ROUTING
if ($page === 'admin') {
    require_once ROOT_PATH . 'modules/admin/index.php'; // Pfad korrigiert, falls es nicht in einem Unterordner ist
    exit();
}


// --- PHASE 4: RENDERING DER REGULÄREN SEITEN ---
// Seitennamen bereinigen, um nur alphanumerische Zeichen, Bindestriche, Unterstriche zu erlauben
$page = preg_replace('/[^a-zA-Z0-9\-_.]/', '', $page);

// Pfad zum Modul
$module_path = ROOT_PATH . 'modules/' . $page . '.php';

// Header der Seite einbinden
require_once ROOT_PATH . 'templates/header.php';

// Modul laden oder 404-Fehler anzeigen
if (file_exists($module_path)) {
    if ($page === 'home') {
        require_once $module_path;
    } else {
        echo '<div class="site-content-wrapper">';
        require_once $module_path;
        echo '</div>';
    }
} else {
    header("HTTP/1.0 404 Not Found");
    echo '<div class="site-content-wrapper">';
    require_once ROOT_PATH . 'modules/404.php';
    echo '</div>';
}

// Footer der Seite einbinden
require_once ROOT_PATH . 'templates/footer.php';

?>