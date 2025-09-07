<?php
// include/asset_handler.php

/**
 * Behandelt Anfragen für statische Assets, die mit einem Unterstrich (_), Bindestrich (-) oder Punkt (.) beginnen.
 * Anfragen mit '_' werden aus dem 'assets/'-Ordner bedient.
 * Anfragen mit '-' werden aus dem 'assets/img/'-Ordner bedient.
 * Anfragen mit '.' werden aus dem 'assets/style/'-Ordner bedient.
 *
 * @param string $page_name Der bereinigte Seitenname aus der URL (das erste Segment nach dem Root).
 * @return void
 */
function handleAssetRequest(string $page_name): void
{
    $assets_folder_base_path = ROOT_PATH . 'assets/';
    $file_name_without_prefix = '';
    $target_folder_path = '';

    // 1. Überprüfen, ob eine Asset-Anfrage vorliegt (beginnt mit '_', '-' oder '.')
    if (str_starts_with($page_name, '_')) {
        $file_name_without_prefix = substr($page_name, 1);
        $target_folder_path = $assets_folder_base_path; // Für '_' direkt im assets-Root
    } elseif (str_starts_with($page_name, '-')) {
        $file_name_without_prefix = substr($page_name, 1);
        $target_folder_path = $assets_folder_base_path . 'img/'; // Für '-' im assets/img-Unterordner
    } elseif (str_starts_with($page_name, '.')) { // NEU: Für Punkt-Präfix
        $file_name_without_prefix = substr($page_name, 1);
        $target_folder_path = $assets_folder_base_path . 'style/'; // Für '.' im assets/style-Unterordner
    } else {
        // Keine Asset-Anfrage, die von dieser Funktion behandelt wird
        return;
    }

    $file_path = $target_folder_path . $file_name_without_prefix;

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
        case 'webp': // Häufig für optimierte Bilder
            header('Content-Type: image/webp');
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