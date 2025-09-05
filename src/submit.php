<?php
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);
    $subject = htmlspecialchars($_POST['subject']);
    $message = htmlspecialchars($_POST['message']);

    // Basic validation
    if (empty($name) || empty($email) || empty($subject) || empty($message) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: error.php");
        exit;
    }

    // --- Database Storage ---
    if ($pdo) { // Check if PDO connection was successful
        try {
            $stmt = $pdo->prepare("INSERT INTO form_submissions (name, email, subject, message) VALUES (:name, :email, :subject, :message)");
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':subject' => $subject,
                ':message' => $message
            ]);
            // Log successful database insertion for debugging if needed
        } catch (PDOException $e) {
            error_log("Database insert failed: " . $e->getMessage());
            // Continue to email sending even if DB insert fails
        }
    } else {
        error_log("Database connection not available, skipping submission logging.");
    }

    // --- Email Sending (using PHP's built-in mail() function) ---
    $to = MAIL_TO_ADDRESS;
    $headers = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_ADDRESS . ">\r\n";
    $headers .= "Reply-To: " . $email . "\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";

    $email_subject = "Neue Kontaktanfrage: " . $subject;
    $email_body = "
        <html>
        <head>
            <title>" . $email_subject . "</title>
        </head>
        <body>
            <h2>Neue Kontaktanfrage</h2>
            <p><strong>Name:</strong> " . $name . "</p>
            <p><strong>E-Mail:</strong> " . $email . "</p>
            <p><strong>Betreff:</strong> " . $subject . "</p>
            <p><strong>Nachricht:</strong><br>" . nl2br($message) . "</p>
            <p>Gesendet am: " . date('d.m.Y H:i:s') . "</p>
        </body>
        </html>
    ";

    if (mail($to, $email_subject, $email_body, $headers)) {
        header("Location: success.php");
        exit;
    } else {
        error_log("Email sending failed for: " . $email);
        header("Location: error.php");
        exit;
    }
} else {
    // Not a POST request, redirect to form
    header("Location: index.php");
    exit;
}
?>
