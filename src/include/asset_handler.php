<?php
// include/asset_handler.php

/**
 * Behandelt Anfragen für statische Assets, die mit einem Unterstrich (_), Bindestrich (-) oder Punkt (.) beginnen.
 * Diese Datei darf KEINE weiteren PHP-Dateien einbinden, da sie sehr früh im Request-Zyklus geladen wird.
 *
 * @param string $page_name Der bereinigte Seitenname aus der URL (das erste Segment nach dem Root).
 * @return void
 */
function handleAssetRequest(string $page_name): void
{
    // ROOT_PATH muss bereits definiert sein (z.B. in config/config.php)
    // Wenn config/config.php nicht geladen ist, würde ROOT_PATH hier einen Fehler verursachen.
    // Daher muss config/config.php VOR asset_handler.php geladen werden.
    if (!defined('ROOT_PATH')) {
        // Dies sollte nicht passieren, wenn index.php korrekt ist.
        // Aber als Fallback oder Debug-Hinweis.
        error_log("ROOT_PATH not defined before asset_handler.php. Check index.php load order.");
        http_response_code(500);
        echo "Server configuration error.";
        exit();
    }

    $assets_folder_base_path = ROOT_PATH . 'assets/';
    $file_name_without_prefix = '';
    $target_folder_path = '';

    // 1. Überprüfen, ob eine Asset-Anfrage vorliegt (beginnt mit '_', '-' oder '.')
    if (str_starts_with($page_name, '_')) {
        $file_name_without_prefix = substr($page_name, 1);
        // Wenn es sich um eine JS-Datei handelt, den Pfad anpassen
        if (str_ends_with($file_name_without_prefix, '.js')) {
            $target_folder_path = $assets_folder_base_path . 'js/';
        } else {
            $target_folder_path = $assets_folder_base_path; // Für '_' direkt im assets-Root
        }
    } elseif (str_starts_with($page_name, '-')) {
        $file_name_without_prefix = substr($page_name, 1);
        $target_folder_path = $assets_folder_base_path . 'img/'; // Für '-' im assets/img-Unterordner
    } elseif (str_starts_with($page_name, '.')) { // Für Punkt-Präfix
        $file_name_without_prefix = substr($page_name, 1);
        $target_folder_path = $assets_folder_base_path . 'style/'; // Für '.' im assets/style-Unterordner
    } else {
        // Keine Asset-Anfrage, die von dieser Funktion behandelt wird
        // Das Skript wird in index.php fortgesetzt.
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
        case 'webp':
            header('Content-Type: image/webp');
            break;
        case 'avif':
            header('Content-Type: image/avif');
            break;
        default:
            header('Content-Type: application/octet-stream');
            break;
    }

    // Datei an den Browser senden
    readfile($file_path);
    exit(); // Wichtig: Beende das Skript nach dem Senden des Assets
}
?>