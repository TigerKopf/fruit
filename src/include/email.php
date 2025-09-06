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
require_once ROOT_PATH . 'config/sensitive_config.php';
require_once ROOT_PATH . 'include/db.php'; // Stelle sicher, dass die DB-Verbindung hier verfügbar ist

// Composer Autoload einbinden. Dies lädt PHPMailer und andere Bibliotheken.
require_once ROOT_PATH . 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!function_exists('sendAppEmail')) {
    /**
     * Sendet eine E-Mail über die konfigurierte SMTP-Verbindung mit einem HTML-Template
     * und protokolliert den Versand in der Datenbank.
     *
     * @param string $to Empfänger-E-Mail-Adresse
     * @param string $subject Betreff der E-Mail
     * @param string $message Der Inhalt der E-Mail (HTML wird erwartet)
     * @param int|null $orderId Optionale Bestell-ID, falls E-Mail zu einer Bestellung gehört
     * @return bool|string True bei Erfolg, Fehlermeldung als String bei Fehler
     */
    function sendAppEmail(string $to, string $subject, string $message, ?int $orderId = null)
    {
        global $pdo; // Datenbankverbindung nutzen
        $mail = new PHPMailer(true);

        $logStatus = 'failed';
        $logErrorMessage = null;

        try {
            // Server-Einstellungen
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_PASSWORD;

            if (defined('MAIL_ENCRYPTION')) {
                switch (strtolower(MAIL_ENCRYPTION)) {
                    case 'ssl':
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                        break;
                    case 'tls':
                    default:
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        break;
                }
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->Port       = MAIL_PORT;

            $fromEmail = MAIL_FROM_EMAIL;
            $atPos = strrpos($fromEmail, '@');
            if ($atPos !== false) {
                $usernamePart = substr($fromEmail, 0, $atPos);
                $domainPart = substr($fromEmail, $atPos + 1);
                if (function_exists('idn_to_ascii')) {
                    $punycodeDomain = idn_to_ascii($domainPart, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
                    if ($punycodeDomain !== false) {
                        $fromEmail = $usernamePart . '@' . $punycodeDomain;
                    } else {
                        error_log("Warnung: idn_to_ascii konnte die Domain '{$domainPart}' nicht in Punycode umwandeln.");
                    }
                } else {
                    error_log("Warnung: PHP intl Erweiterung nicht geladen. 'From'-Adresse könnte Probleme mit Sonderzeichen verursachen.");
                }
            }

            $mail->setFrom($fromEmail, MAIL_FROM_NAME);
            $mail->addAddress($to);

            // Inhalt
            $mail->CharSet = 'UTF-8';
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $messageWithCss = '
            <!DOCTYPE html>
            <html>
            <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; color: #333333; }
            .container { width: 100%; max-width: 600px; margin: 0 auto; padding: 20px; text-align: center; background-color: #ffffff; box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.1); border-radius: 8px; }
            .message-box { border: 1px solid #e0e0e0; padding: 20px; border-radius: 5px; background-color: #ffffff; text-align: left; line-height: 1.6; }
            p { margin-bottom: 1em; }
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
            $mail->AltBody = strip_tags($message);

            $mail->send();
            $logStatus = 'sent';
            return true;

        } catch (Exception $e) {
            $logErrorMessage = $mail->ErrorInfo;
            error_log("E-Mail konnte nicht gesendet werden. Mailer Error: {$logErrorMessage}");
            return "E-Mail konnte nicht gesendet werden. Mailer Error: {$logErrorMessage}";
        } finally {
            // E-Mail-Versand protokollieren
            if (isset($pdo)) {
                try {
                    $stmtLog = $pdo->prepare("INSERT INTO email_logs (to_email, subject, body_snippet, status, error_message, order_id) VALUES (:to_email, :subject, :body_snippet, :status, :error_message, :order_id)");
                    $stmtLog->execute([
                        ':to_email' => $to,
                        ':subject' => $subject,
                        ':body_snippet' => substr(strip_tags($message), 0, 500) . '...', // Snippet des Inhalts
                        ':status' => $logStatus,
                        ':error_message' => $logErrorMessage,
                        ':order_id' => $orderId
                    ]);
                } catch (PDOException $e) {
                    error_log("Fehler beim Protokollieren der E-Mail in der Datenbank: " . $e->getMessage());
                }
            } else {
                error_log("PDO-Verbindung nicht verfügbar für E-Mail-Protokollierung.");
            }
        }
    }
}