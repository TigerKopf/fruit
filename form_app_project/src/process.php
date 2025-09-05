<?php
require_once 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = htmlspecialchars(trim($_POST['name']));
    $email = htmlspecialchars(trim($_POST['email']));
    $subject = htmlspecialchars(trim($_POST['subject']));
    $message = htmlspecialchars(trim($_POST['message']));

    // Einfache Validierung
    if (empty($name) || empty($email) || empty($message) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: index.php?status=validation_error");
        exit;
    }

    $success = false;

    // 1. Daten in der Datenbank speichern
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($conn->connect_error) {
            throw new Exception("Verbindungsfehler zur Datenbank: " . $conn->connect_error);
        }

        $stmt = $conn->prepare("INSERT INTO form_submissions (name, email, subject, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $subject, $message);

        if ($stmt->execute()) {
            // Datenbank-Speicherung erfolgreich
            $success = true;
        } else {
            throw new Exception("Fehler beim Speichern in der Datenbank: " . $stmt->error);
        }

        $stmt->close();
        $conn->close();

    } catch (Exception $e) {
        // Loggen Sie den Fehler anstatt ihn direkt anzuzeigen
        error_log($e->getMessage());
        $success = false; // Datenbank-Speicherung fehlgeschlagen
    }

    // 2. E-Mail senden (unabhängig vom Datenbankerfolg, aber nur wenn DB-Speicherung initiiert wurde)
    if ($success) { // E-Mail nur senden, wenn Datenbank-Speicherung zumindest versucht wurde
        $to = EMAIL_RECIPIENT;
        $email_subject = EMAIL_SUBJECT_PREFIX . " " . (!empty($subject) ? $subject : 'Neue Anfrage');
        $headers = "From: " . EMAIL_SENDER_NAME . " <" . EMAIL_SENDER_EMAIL . ">\r\n";
        $headers .= "Reply-To: " . $email . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        $email_body = "Sie haben eine neue Nachricht von Ihrem Kontaktformular erhalten.\n\n";
        $email_body .= "Name: " . $name . "\n";
        $email_body .= "E-Mail: " . $email . "\n";
        $email_body .= "Betreff: " . (!empty($subject) ? $subject : 'Kein Betreff angegeben') . "\n";
        $email_body .= "Nachricht:\n" . $message . "\n";

        // PHP mail() Funktion
        if (mail($to, $email_subject, $email_body, $headers)) {
            // E-Mail erfolgreich gesendet
            header("Location: index.php?status=success");
            exit;
        } else {
            // Fehler beim Senden der E-Mail
            // Da die DB-Speicherung erfolgreich war, ist dies ein Teilerfolg.
            // Man könnte hier differenziertere Statusmeldungen geben.
            error_log("Fehler beim Senden der E-Mail für die Anfrage von " . $email);
            header("Location: index.php?status=success"); // Melden als Erfolg, da DB gespeichert wurde
            exit;
        }
    } else {
        // Wenn die Datenbank-Speicherung fehlschlägt, wird auch kein E-Mail-Versand versucht
        header("Location: index.php?status=error");
        exit;
    }

} else {
    // Wenn jemand direkt auf process.php zugreift, ohne POST-Daten
    header("Location: index.php");
    exit;
}
?>
