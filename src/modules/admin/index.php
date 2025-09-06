<?php
// modules/admin/index.php
// Dies ist der Admin-Router

// Da index.php bereits session_start() aufgerufen hat, ist es hier nicht nötig,
// aber wir überprüfen vorsichtshalber.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Benötigte Konfiguration und Funktionen laden (ROOT_PATH kommt von der Haupt-index.php)
require_once ROOT_PATH . 'config/sensitive_config.php';
require_once ROOT_PATH . 'include/db.php';
require_once ROOT_PATH . 'include/email.php'; // Für mögliche E-Mail-Funktionen im Admin
require_once ROOT_PATH . 'include/helpers.php'; // Für formatEuroCurrency

global $pdo; // Sicherstellen, dass $pdo verfügbar ist
if (!isset($pdo)) {
    $pdo = getDbConnection();
}

// --- GLOBALE VARIABLEN FÜR ADMIN-KONTEXT ---
$isAdminLoggedIn = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);
$loginError = '';
$currentSection = $_GET['section'] ?? 'dashboard';
$actionStatus = $_GET['status'] ?? ''; // Für Erfolgs-/Fehlermeldungen nach Aktionen
$actionMessage = $_GET['msg'] ?? ''; // Zusätzliche Nachricht bei Fehlern
$id = (int)($_GET['id'] ?? 0); // Allgemeine ID für Aktionen
$action = $_GET['action'] ?? '';


// --- VORVERARBEITUNG VON POST- UND GET-ANFRAGEN (VOR JEDER HTML-AUSGABE) ---

// 1. Logout-Logik (GET-Anfrage)
if ($action === 'logout') {
    session_destroy();
    header('Location: /admin'); // Redirect zur Login-Seite
    exit();
}

// 2. Login-Logik (POST-Anfrage)
if (isset($_POST['admin_password']) && !$isAdminLoggedIn) {
    if (password_verify($_POST['admin_password'], ADMIN_PASSWORD_HASH)) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: /admin'); // Redirect nach erfolgreichem Login, um POST-Daten zu vermeiden
        exit();
    } else {
        $loginError = 'Falsches Passwort.';
    }
}

// Wenn nicht eingeloggt, zeige Login-Formular und beende
if (!$isAdminLoggedIn) {
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login</title>
        <link rel="stylesheet" href="/assets/styles.css"> <!-- Lade dein Haupt-CSS -->
        <style>
            /* Spezifische Login-Stile, falls sie nicht im Haupt-CSS sind */
            body { font-family: 'Inter', Arial, sans-serif; background-color: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
            .login-container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); text-align: center; width: 350px; }
            .login-container h2 { margin-bottom: 25px; color: #333; }
            .login-container input[type="password"] { width: calc(100% - 24px); padding: 12px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 6px; font-size: 1em; }
            .login-container button { background-color: #4285f4; color: white; padding: 12px 25px; border: none; border-radius: 6px; cursor: pointer; font-size: 1em; transition: background-color 0.2s ease; }
            .login-container button:hover { background-color: #357ae8; }
            .login-container .error { color: #e44d26; margin-bottom: 15px; }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h2>Admin Login</h2>
            <?php if ($loginError): ?>
                <p class="error"><?php echo htmlspecialchars($loginError); ?></p>
            <?php endif; ?>
            <form method="POST">
                <input type="password" name="admin_password" placeholder="Passwort" required>
                <button type="submit">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit(); // Beende Skript, wenn nicht eingeloggt
}

// --- Wenn eingeloggt, lade den Admin-Header und die spezifische Sektion ---
// Admin Header (sidebar und oberer Teil des Contents)
require_once ROOT_PATH . 'templates/header.php'; // Normaler Header des Shops

// Admin-Layout-Wrapper öffnen
echo '<div class="admin-container">';
echo '<div class="admin-sidebar">';
echo '<h2>Admin Panel</h2>';
echo '<ul>';
// Links für die Navigation
$adminNavItems = [
    'dashboard' => 'Dashboard',
    'orders' => 'Bestellungen',
    'customers' => 'Kunden',
    'payments' => 'Zahlungen',
    'products' => 'Produkte',
    'categories' => 'Kategorien',
    'pickup_dates' => 'Abholtermine',
    'email_logs' => 'E-Mail Logs',
];
foreach ($adminNavItems as $key => $label) {
    $activeClass = ($currentSection == $key) ? 'active' : '';
    echo '<li><a href="?section=' . htmlspecialchars($key) . '" class="' . $activeClass . '">' . htmlspecialchars($label) . '</a></li>';
}
echo '<li><a href="?action=logout">Logout</a></li>';
echo '</ul>';
echo '</div>'; // .admin-sidebar schließen

echo '<div class="admin-content">';
echo '<h1>' . ucfirst(str_replace('_', ' ', $currentSection)) . ' Verwaltung</h1>';

// Erfolgs-/Fehlermeldungen anzeigen
if ($actionStatus === 'success'): ?>
    <div class="alert success"><?php echo htmlspecialchars($actionMessage ?: 'Aktion erfolgreich ausgeführt.'); ?></div>
<?php elseif ($actionStatus === 'error'): ?>
    <div class="alert error"><?php echo htmlspecialchars($actionMessage ?: 'Fehler bei der Aktion.'); ?></div>
<?php endif;

// Lade die spezifische Admin-Sektion
$adminSectionPath = ROOT_PATH . 'modules/admin/' . $currentSection . '.php';

if (file_exists($adminSectionPath)) {
    require_once $adminSectionPath;
} else {
    echo '<p class="alert error">Admin-Sektion nicht gefunden.</p>';
}

echo '</div>'; // .admin-content schließen
echo '</div>'; // .admin-container schließen

// Footer laden
require_once ROOT_PATH . 'templates/footer.php';
?>