<?php
// src/include/startup.php

/**
 * Initialisierungs-Einstellungen für die Anwendung.
 */

// Stellen Sie sicher, dass discord.php geladen ist, bevor wir es hier verwenden
// (loader.php sollte dies bereits tun, aber defensive Programmierung schadet nicht)
if (!function_exists('sendDiscordWebhook')) {
    require_once ROOT_PATH . 'include/discord.php';
}


$is_localhost = (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) || strpos($_SERVER['HTTP_HOST'], 'localhost') !== false);

if ($is_localhost) {
    // Auf localhost: detaillierte Fehler anzeigen
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    // In einer Live-Umgebung: Fehler nicht anzeigen, aber loggen und an Discord senden
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL); // Fehler trotzdem loggen und an Custom Handler senden
    ini_set('log_errors', 1); // PHP soll Fehler trotzdem in das Log-File schreiben (zusätzlich zu Discord)
    ini_set('error_log', ROOT_PATH . 'logs/php_errors.log'); // Stelle sicher, dass der logs-Ordner existiert und beschreibbar ist

    // Benutzerdefinierten Fehlerhandler registrieren
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        // Ignoriere @-Suppressor oder bestimmte Fehlerlevel, falls gewünscht
        if (!(error_reporting() & $errno)) {
            return false; // PHP soll den internen Error-Handler weiter ausführen
        }

        $error_type = "Fehler";
        switch ($errno) {
            case E_ERROR:
            case E_PARSE:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                $error_type = "Kritischer Fehler";
                break;
            case E_WARNING:
            case E_USER_WARNING:
            case E_COMPILE_WARNING:
            case E_RECOVERABLE_ERROR:
                $error_type = "Warnung";
                break;
            case E_NOTICE:
            case E_USER_NOTICE:
            case E_STRICT:
                $error_type = "Hinweis";
                break;
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                $error_type = "Veraltet";
                break;
        }

        $title = "PHP " . $error_type . " im Backend";
        $description = "**Meldung:** " . htmlspecialchars($errstr) . "\n"
                     . "**Datei:** `" . htmlspecialchars($errfile) . "`\n"
                     . "**Zeile:** `" . $errline . "`\n"
                     . "**Fehlernummer:** `" . $errno . "`";

        // Sende nur relevante Fehler an Discord (z.B. keine Notices in Prod)
        if ($errno !== E_NOTICE && $errno !== E_USER_NOTICE && $errno !== E_DEPRECATED && $errno !== E_USER_DEPRECATED) {
             sendDiscordWebhook("Ein Problem ist aufgetreten!", $title, $description, "PHP Error Handler", "error");
        }

        // Rückgabe false, damit der PHP-Standardfehlerhandler auch ausgeführt wird,
        // um z.B. das Logging in 'php_errors.log' zu gewährleisten.
        return false;
    });

    // Benutzerdefinierten Exception-Handler registrieren
    set_exception_handler(function($exception) {
        $title = "Unerwartete Exception im Backend";
        $description = "**Meldung:** " . htmlspecialchars($exception->getMessage()) . "\n"
                     . "**Datei:** `" . htmlspecialchars($exception->getFile()) . "`\n"
                     . "**Zeile:** `" . $exception->getLine() . "`\n"
                     . "**Code:** `" . $exception->getCode() . "`";

        sendDiscordWebhook("Ein unerwarteter Fehler ist aufgetreten!", $title, $description, "PHP Exception Handler", "error");

        // Optional: Hier könnte man eine generische Fehlerseite anzeigen
        // error_log("Uncaught exception: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine());
        // http_response_code(500);
        // echo "Ein unerwarteter Fehler ist aufgetreten.";
    });
}