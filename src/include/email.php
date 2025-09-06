<?php
// include/email.php

/**
 * E-Mail-Funktionen unter Verwendung von PHPMailer.
 *
 * Bindet die sensible Konfiguration ein und stellt eine Funktion zum Senden von E-Mails bereit.
 */

// Diese Zeilen sind gut für die Entwicklung, um PHP-Fehler anzuzeigen.
// In einer Produktionsumgebung sollten diese Zeilen entfernt oder entsprechend angepasst werden,
// um Fehler in Log-Dateien zu schreiben und nicht direkt anzuzeigen.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Sensible Konfiguration einbinden
// ROOT_PATH muss im Haupt-Projekt-Root definiert sein (z.B. in index.php)
// und auf das Verzeichnis '/home/ano/Dokumente/GitHub/fruit/' zeigen.
// Dieser Pfad geht davon aus, dass 'sensitive_config.php' direkt im ROOT_PATH liegt.
require_once ROOT_PATH . 'config/sensitive_config.php';
// Falls 'sensitive_config.php' in 'src/config/' liegt, wäre es:
// require_once ROOT_PATH . 'src/config/sensitive_config.php';


// Composer Autoload einbinden. Dies lädt PHPMailer und andere Bibliotheken.
// Es wird davon ausgegangen, dass der 'vendor'-Ordner direkt unter ROOT_PATH liegt.
require_once ROOT_PATH . 'vendor/autoload.php';
 
// Die einzelnen PHPMailer-Klassen müssen nicht mehr separat eingebunden werden,
// da der Composer Autoloader dies übernimmt.
// Die 'use'-Anweisungen sind jedoch weiterhin erforderlich, um die Klassen
// im aktuellen Namespace direkt ansprechen zu können.
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// Die Klasse PHPMailer\PHPMailer\SMTP ist nicht direkt nötig, da die Konstanten
// (wie ENCRYPTION_STARTTLS) über die PHPMailer-Klasse selbst erreichbar sind.

if (!function_exists('sendAppEmail')) {
    /**
     * Sendet eine E-Mail über die konfigurierte SMTP-Verbindung mit einem HTML-Template.
     *
     * @param string $to Empfänger-E-Mail-Adresse
     * @param string $subject Betreff der E-Mail
     * @param string $message Der Inhalt der E-Mail (HTML wird erwartet)
     * @return bool|string True bei Erfolg, Fehlermeldung als String bei Fehler
     */
    function sendAppEmail(string $to, string $subject, string $message) // Rückgabetyp wurde angepasst (mixed)
    {
        $mail = new PHPMailer(true); // Aktiviert Exceptions für detaillierte Fehlerbehandlung
        try {
            // Server-Einstellungen
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;       // Dein SMTP Host
            $mail->SMTPAuth   = true;            // SMTP Authentifizierung aktivieren
            $mail->Username   = MAIL_USERNAME;   // SMTP Benutzername
            $mail->Password   = MAIL_PASSWORD;   // SMTP Passwort
            
            // Verschlüsselung basierend auf MAIL_ENCRYPTION setzen
            if (defined('MAIL_ENCRYPTION')) {
                switch (strtolower(MAIL_ENCRYPTION)) {
                    case 'ssl':
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                        break;
                    case 'tls':
                    default: // Standard auf TLS, falls nicht definiert oder unbekannt
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        break;
                }
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Standard: TLS
            }
            
            $mail->Port       = MAIL_PORT;       // TCP Port zum Verbinden

            // Optional: Wenn du Probleme mit SSL-Zertifikaten auf einer lokalen Entwicklungsumgebung hast,
            // kannst du diese SMTPOptions hinzufügen, um die Überprüfung zu deaktivieren.
            // NICHT FÜR PRODUKTION EMPFOHLEN!
            // Löse dieses Problem nur, wenn du sicher bist, dass der SMTP-Server vertrauenswürdig ist
            // und du keine anderen Möglichkeiten hast (z.B. korrektes CA-Zertifikat hinzufügen).
            // $mail->SMTPOptions = [
            //     'ssl' => [
            //         'verify_peer' => false,
            //         'verify_peer_name' => false,
            //         'allow_self_signed' => true
            //     ]
            // ];


            // NEU: Absender-E-Mail-Adresse für IDN (Internationalized Domain Name) anpassen
            $fromEmail = MAIL_FROM_EMAIL;
            // E-Mail-Adresse in zwei Teile splitten: Benutzername und Domain
            $atPos = strrpos($fromEmail, '@');
            if ($atPos !== false) {
                $usernamePart = substr($fromEmail, 0, $atPos);
                $domainPart = substr($fromEmail, $atPos + 1);
                
                // Prüfen, ob die intl-Erweiterung geladen ist, bevor idn_to_ascii verwendet wird
                if (function_exists('idn_to_ascii')) {
                    // Domain in Punycode umwandeln, wenn Sonderzeichen vorhanden sind
                    $punycodeDomain = idn_to_ascii($domainPart, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
                    if ($punycodeDomain !== false) {
                        $fromEmail = $usernamePart . '@' . $punycodeDomain;
                    } else {
                        // idn_to_ascii fehlgeschlagen, logge dies und verwende Original
                        error_log("Warnung: idn_to_ascii konnte die Domain '{$domainPart}' nicht in Punycode umwandeln. Verwende die Originaladresse.");
                    }
                } else {
                    // intl-Erweiterung fehlt, logge dies
                    error_log("Warnung: PHP intl Erweiterung nicht geladen. 'From'-Adresse '" . MAIL_FROM_EMAIL . "' könnte Probleme mit Sonderzeichen verursachen.");
                    // Fallback auf die Originaladresse
                }
            }
            // Ansonsten bleibt $fromEmail unverändert, wenn kein '@' gefunden wurde oder es keine IDN ist

            // Empfänger-Details
            $mail->setFrom($fromEmail, MAIL_FROM_NAME); // Hier die angepasste Adresse verwenden
            $mail->addAddress($to); // Empfänger hinzufügen

            // Inhalt
            $mail->CharSet = 'UTF-8'; // Zeichenkodierung auf UTF-8 setzen
            $mail->isHTML(true);      // E-Mail-Format auf HTML setzen

            $mail->Subject = $subject;

            // HTML-E-Mail-Vorlage mit dem übergebenen Inhalt
            $messageWithCss = '
            <!DOCTYPE html>
            <html>
            <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 0;
                background-color: #f4f4f4;
                color: #333333;
            }
            .container {
                width: 100%;
                max-width: 600px; /* Max-Breite für bessere Darstellung auf Desktop und Mobile */
                margin: 0 auto;
                padding: 20px;
                text-align: center;
                background-color: #ffffff;
                box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.1);
                border-radius: 8px;
            }
            .message-box {
                border: 1px solid #e0e0e0;
                padding: 20px;
                border-radius: 5px;
                background-color: #ffffff;
                text-align: left; /* Text innerhalb der Box linksbündig */
                line-height: 1.6;
            }
            p { margin-bottom: 1em; } /* Basic paragraph styling */
            </style>
            </head>
            <body>
            <div class="container">
                <div class="message-box">
                    ' . $message . '
                </div>
            </div>
            </body>
            </html>
            ';
            
            $mail->Body = $messageWithCss;
            $mail->AltBody = strip_tags($message); // Alternativer Textkörper für Nicht-HTML-Clients

            // E-Mail senden
            $mail->send();
    
            return true; // Ergebnis des E-Mail-Versands zurückgeben
        } catch (Exception $e) {
            // Fehler in das Server-Log schreiben
            error_log("E-Mail konnte nicht gesendet werden. Mailer Error: {$mail->ErrorInfo}");
            // Fehlermeldung als String zurückgeben, um sie auf der Webseite anzuzeigen
            return "E-Mail konnte nicht gesendet werden. Mailer Error: {$mail->ErrorInfo}";
        }
    }
}