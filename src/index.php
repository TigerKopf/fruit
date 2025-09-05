<?php
// index.php

// 1. Konfiguration oder globale Einstellungen (optional, aber nützlich)
//    Hier könnten z.B. Datenbankverbindungen, Konstanten oder Pfade definiert werden.
define('BASE_PATH', __DIR__); // Definiert den Basispfad des Projekts
define('APP_NAME', 'Meine Awesome Website');

// 2. Fehlerreporting (hilfreich während der Entwicklung)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 3. Autoloading (wenn du Klassen verwendest)
//    Für einfache Projekte kannst du dies weglassen.
//    Für komplexere Projekte mit vielen Klassen ist ein Autoloader sehr empfehlenswert.
//    Beispiel für einen PSR-4 Autoloader (benötigt Composer):
/*
require_once 'vendor/autoload.php';
*/

// --- Start der HTML-Ausgabe ---

// 4. Header einbinden
//    Hier kommen Dinge wie <head>-Tags, CSS-Links, Meta-Tags und der obere Teil der Navigation
include_once BASE_PATH . '/includes/header.php';

// 5. Navigation/Menü einbinden
//    Die Hauptnavigation der Website
include_once BASE_PATH . '/includes/navigation.php';

// 6. Hauptinhaltsbereich
echo '<main id="content" class="container mt-4">';

// 7. Dynamisches Laden von Seiteninhalten
//    Über den GET-Parameter 'page' wird gesteuert, welche Inhaltsdatei geladen wird.
$page = isset($_GET['page']) ? $_GET['page'] : 'home'; // Standardseite ist 'home'

// Verhindert Directory Traversal und erlaubt nur definierte Seiten
$allowed_pages = [
    'home'      => 'pages/home.php',
    'about'     => 'pages/about.php',
    'contact'   => 'pages/contact.php',
    'products'  => 'pages/products.php',
    // Füge hier weitere erlaubte Seiten hinzu
];

if (array_key_exists($page, $allowed_pages)) {
    // Wenn die Seite erlaubt ist, binde sie ein
    include_once BASE_PATH . '/' . $allowed_pages[$page];
} else {
    // Wenn die Seite nicht existiert oder nicht erlaubt ist, lade eine 404-Seite
    include_once BASE_PATH . '/pages/404.php';
}

echo '</main>'; // Ende des Hauptinhaltsbereichs

// 8. Footer einbinden
//    Hier kommen Dinge wie Copyright-Informationen, Skripte, etc.
include_once BASE_PATH . '/includes/footer.php';

?>