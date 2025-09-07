<?php

/**
 * Kern-Datei der Anwendung
 * Diese Datei verarbeitet alle Anfragen dank .htaccess
 */

// --- SETUP PHASE ---
// Konfigurationsdatei einbinden (Bleibt hier, da sie grundlegende Konstanten wie ROOT_PATH definiert)
require_once __DIR__ . '/config/config.php';

// Alle Hilfsdateien und Initialisierungen aus dem 'include'-Ordner laden
// Dies bindet startup.php, asset_handler.php, db.php, email.php, helpers.php und alle anderen hinzugefügten Dateien ein.
require_once __DIR__ . '/include/loader.php';

// Composer Autoload einbinden. Dies ist essenziell für PHPMailer.
// Stelle sicher, dass der 'vendor'-Ordner direkt unter deinem ROOT_PATH liegt.
require_once __DIR__  . '/vendor/autoload.php';

// Session starten (wichtig für den Warenkorb und Admin-Login)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


// --- REQUEST PARSING PHASE ---
// Angeforderte URI extrahieren
// Beispiel: /ueber-uns -> ueber-uns
$request_uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

// Segmente der URI aufteilen
$segments = explode('/', $request_uri);

// Das erste Segment als Modul-/Seitennamen verwenden, Standard ist 'home'
$page = !empty($segments[0]) ? $segments[0] : 'home';


// --- ASSET HANDLING PHASE ---
// Prüfen, ob eine Asset-Anfrage vorliegt und diese behandeln
// Die Funktion handleAssetRequest() ist in include/asset_handler.php definiert.
// Sie wird nur aktiv, wenn $page_name mit '_' beginnt (z.B. /_favicon.ico).
// Eine Anfrage wie /api/cart_process.php oder /admin wird hier nicht behandelt.
handleAssetRequest($page);


// --- API ROUTING PHASE ---
// Wenn das erste Segment 'api' ist, versuchen wir, die entsprechende API-Datei zu laden.
if ($page === 'api' && isset($segments[1])) {
    $api_file = ROOT_PATH . 'api/' . $segments[1];
    if (file_exists($api_file)) {
        require_once $api_file;
        exit(); // Wichtig: Beende das Skript nach dem Ausführen der API-Datei
    } else {
        header("HTTP/1.0 404 Not Found");
        require_once ROOT_PATH . 'modules/404.php';
        exit();
    }
}

// --- ADMIN ROUTING PHASE (NEU) ---
// Wenn das erste Segment 'admin' ist, laden wir den Admin-Einstiegspunkt.
if ($page === 'admin') {
    // Admin-Modul wird direkt geladen und hat seinen eigenen Container-Stil
    require_once ROOT_PATH . 'modules/admin.php';
    exit(); // Wichtig: Beende das Skript nach dem Ausführen der Admin-Seite
}


// --- PAGE / MODULE LOADING PHASE ---
// Seitennamen bereinigen, um nur alphanumerische Zeichen, Bindestriche, Unterstriche zu erlauben
$page = preg_replace('/[^a-zA-Z0-9\-_.]/', '', $page);

// Pfad zum Modul
$module_path = ROOT_PATH . 'modules/' . $page . '.php';

// Header der Seite einbinden
require_once ROOT_PATH . 'templates/header.php';

// Modul laden oder 404-Fehler anzeigen
if (file_exists($module_path)) {
    if ($page === 'home') {
        // Die Homepage hat eine spezielle Hero-Sektion und der Inhalt
        // wird direkt in #page-content-wrapper gerendert.
        require_once $module_path;
    } else {
        // Für alle anderen Seiten: Inhalt in .site-content-wrapper wrappen.
        // Beachte, dass .site-content-wrapper jetzt ein div ist, nicht mehr <main>.
        // <main> ist ein semantisches Element innerhalb dieses Wrappers, wenn nötig.
        echo '<div class="site-content-wrapper">';
        require_once $module_path;
        echo '</div>';
    }
} else {
    // Wenn das Modul nicht existiert, 404-Status senden
    header("HTTP/1.0 404 Not Found");
    echo '<div class="site-content-wrapper">'; // 404-Seite auch in Div für konsistentes Layout
    require_once ROOT_PATH . 'modules/404.php';
    echo '</div>';
}

// Footer der Seite einbinden
require_once ROOT_PATH . 'templates/footer.php';

?>