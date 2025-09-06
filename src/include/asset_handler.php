<?php
// include/asset_handler.php

/**
 * Behandelt Anfragen für statische Assets, die mit einem Unterstrich beginnen (z.B. /_favicon.ico).
 *
 * @param string $page_name Der bereinigte Seitenname aus der URL.
 * @return void
 */
function handleAssetRequest(string $page_name): void
{
    // 1. Überprüfen, ob $page_name mit einem Unterstrich beginnt
    if (str_starts_with($page_name, '_')) {
        // 2. Pfad zum Assets-Ordner bestimmen
        // ROOT_PATH muss in config.php definiert sein
        $assets_folder_path = ROOT_PATH . 'assets/';

        // Dateinamen ohne den führenden Unterstrich
        $file_name = substr($page_name, 1);
        $file_path = $assets_folder_path . $file_name;

        // Prüfen, ob die Datei existiert
        if (!file_exists($file_path)) {
            http_response_code(404);
            echo "Asset not found.";
            exit();
        }

        // MIME-Typ basierend auf der Dateierweiterung setzen
        $file_extension = pathinfo($file_path, PATHINFO_EXTENSION);

        switch ($file_extension) {
            case 'css':
                header('Content-Type: text/css');
                break;
            case 'js':
                header('Content-Type: application/javascript');
                break;
            case 'ico':
                header('Content-Type: image/x-icon');
                break;
            case 'png':
                header('Content-Type: image/png');
                break;
            case 'jpg':
            case 'jpeg':
                header('Content-Type: image/jpeg');
                break;
            case 'gif':
                header('Content-Type: image/gif');
                break;
            case 'svg':
                header('Content-Type: image/svg+xml');
                break;
            default:
                // Standardmäßig als Binärdaten senden oder Fehlerbehandlung
                header('Content-Type: application/octet-stream');
                break;
        }

        // Datei an den Browser senden
        readfile($file_path);
        exit(); // Wichtig: Beende das Skript nach dem Senden des Assets
    }
}
?>