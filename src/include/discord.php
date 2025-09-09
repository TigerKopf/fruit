<?php
// src/include/discord.php

/**
 * Holt die vollständige aktuelle URL.
 * @return string Die aktuelle URL.
 */
if (!function_exists('getCurrentURL')) {
    function getCurrentURL() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $uri = $_SERVER['REQUEST_URI'];
        return $protocol . $host . $uri;
    }
}

/**
 * Extrahiert die Domain aus einer vollständigen URL.
 * @param string $url Die URL, aus der die Domain extrahiert werden soll.
 * @return string Die Domain (z.B. "www.example.com").
 */
if (!function_exists('getDomain')) {
    function getDomain($url) {
        $parsedUrl = parse_url($url);
        return $parsedUrl['host'] ?? '';
    }
}

if (!function_exists('sendDiscordWebhook')) {
    function sendDiscordWebhook($message, $title, $description, $sender, $type) {

        $url = getCurrentURL();
        $domain = getDomain($url);

        // Environment-spezifische Webhook-URLs
        if ($domain === 'www.xn--frch-1ra.de') { // Production URL
            $webhookUrl = 'https://discord.com/api/webhooks/1205814879013961738/e9CVnoC04TTkc2Bfp2ePDp4aLYYUDIcvCpj5jqGS9LBvT9rGSj-FMLoTU50kcfSp-46y';
        } else { // Development/Localhost/Other URL
            $webhookUrl = 'https://discord.com/api/webhooks/1207046107113201754/0DOJTmmiOtbZHchtcfKhfKsrXbGsEkkcQU7w9LOJ3la3V5-2uLJ_Wkb-5_QhPF6AMzQV';
        }

        // Farben für verschiedene Nachrichtentypen definieren
        $colors = [
            'login' => 8454143, // Grün für Login
            'register' => 255, // Blau für Registrierung
            'order' => 65280, // Grün für Bestellungen
            'reorder' => 16754432, // Rot für Reorder
            'move' => 1118481, // Grau für Bewegt
            'error' => 16711680, // Rot für Fehler
            'warning' => 16776960, // Gelb für Warnungen
            'info' => 3447003, // Hellblau für Infos
        ];

        // Prüfen, ob der Nachrichtentyp unterstützt wird
        if (!array_key_exists($type, $colors)) {
            // Fallback auf 'info' oder 'error' wenn unbekannter Typ
            $type = 'info';
            // Optional: Log this unexpected type somewhere
        }

        // Zeitstempel generieren
        $timestamp = date('c');

        // JSON-Daten für die Discord-Nachricht vorbereiten
        $data = [
            'username' => 'Jungeee', // Your bot's username on Discord
            'content' => $message,
            'embeds' => [
                [
                    'title' => $title,
                    'description' => $description,
                    'fields' => [
                        [
                            'name' => 'Absender',
                            'value' => $sender,
                            'inline' => true
                        ],
                        [
                            'name' => 'Umgebung',
                            'value' => ($domain === 'www.xn--frch-1ra.de' ? 'Produktion' : 'Entwicklung/Test'),
                            'inline' => true
                        ],
                        [
                            'name' => 'URL',
                            'value' => $url,
                            'inline' => false
                        ]
                    ],
                    'color' => $colors[$type],
                    'timestamp' => $timestamp
                ]
            ]
        ];

        // JSON in ein String-Format umwandeln
        $jsonData = json_encode($data);

        // HTTP-Anfrage vorbereiten
        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // Anfrage ausführen
        $response = curl_exec($ch);

        // Antwort überprüfen (optional, für Debugging)
        if ($response === false) {
            error_log('Fehler beim Senden der Discord-Nachricht: ' . curl_error($ch));
        }

        // CURL-Verbindung schließen
        curl_close($ch);
    }
}