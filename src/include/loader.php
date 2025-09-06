<?php
// include/loader.php
// Dieser Loader bindet alle anderen .php-Dateien im aktuellen Verzeichnis ('include'-Ordner) ein.

$include_dir = __DIR__; // Das aktuelle Verzeichnis (der 'include'-Ordner)

foreach (glob($include_dir . '/*.php') as $filename) {
    // Sicherstellen, dass der Loader sich nicht selbst einbindet
    if (basename($filename) !== 'loader.php') {
        require_once $filename;
    }
}
?>