<?php
// api/checkout_process.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Benötigte Konfiguration und Funktionen laden
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'include/db.php'; // Stellt getDbConnection() und $pdo bereit
require_once ROOT_PATH . 'include/email.php'; // Stellt sendAppEmail() bereit

/**
 * Formatiert einen Betrag in Euro mit spezifischen Regeln für Nachkommastellen.
 * Zeigt zwei Nachkommastellen nur an, wenn sie nicht .00 sind.
 *
 * @param float $amount Der zu formatierende Betrag.
 * @return string Der formatierte Betrag mit Euro-Symbol.
 */
function formatEuroCurrency(float $amount): string {
    // Überprüfen, ob der Betrag ganze Zahlen hat (keine Nachkommastellen oder .00)
    if (fmod($amount, 1.0) == 0) {
        return number_format($amount, 0, ',', '.') . ' €';
    } else {
        // Andernfalls mit zwei Nachkommastellen formatieren
        return number_format($amount, 2, ',', '.') . ' €';
    }
}


$response = ['success' => false, 'message' => ''];

try {
    $pdo = getDbConnection(); // Sicherstellen, dass die Verbindung besteht
    $pdo->beginTransaction(); // Transaktion starten

    if (empty($_SESSION['cart'])) {
        throw new Exception("Ihr Warenkorb ist leer. Keine Bestellung möglich.");
    }

    // Eingabedaten validieren und bereinigen
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $phone = trim($_POST['phone'] ?? '');
    $paymentMethod = $_POST['paymentMethod'] ?? ''; // 'bank_transfer' oder 'cash'
    $pickupDateId = (int)($_POST['pickupDate'] ?? 0);
    $cartTotalFromClient = (float)($_POST['cart_total'] ?? 0);

    if (empty($firstName) || empty($lastName) || !$email || empty($paymentMethod) || $pickupDateId <= 0) {
        throw new Exception("Bitte füllen Sie alle erforderlichen Felder aus.");
    }
    if (!in_array($paymentMethod, ['bank_transfer', 'cash'])) {
        throw new Exception("Ungültige Zahlungsmethode ausgewählt.");
    }

    // 1. Abholtermin überprüfen
    $stmtPickup = $pdo->prepare("SELECT pickup_datetime, location FROM pickup_dates WHERE pickup_date_id = :id AND is_active = TRUE AND pickup_datetime >= NOW()");
    $stmtPickup->execute([':id' => $pickupDateId]);
    $pickupDateInfo = $stmtPickup->fetch();

    if (!$pickupDateInfo) {
        throw new Exception("Der ausgewählte Abholtermin ist ungültig oder nicht mehr verfügbar.");
    }

    // 2. Benutzer finden oder erstellen
    $userId = null;
    $stmtUser = $pdo->prepare("SELECT user_id FROM users WHERE email = :email");
    $stmtUser->execute([':email' => $email]);
    $existingUser = $stmtUser->fetch();

    if ($existingUser) {
        $userId = $existingUser['user_id'];
        // Optional: Update user info if different
        $stmtUpdateUser = $pdo->prepare("UPDATE users SET first_name = :first_name, last_name = :last_name, phone_number = :phone_number, updated_at = CURRENT_TIMESTAMP WHERE user_id = :user_id");
        $stmtUpdateUser->execute([
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':phone_number' => $phone,
            ':user_id' => $userId
        ]);
    } else {
        // Neuen Benutzer anlegen (vereinfacht, kein Passwort oder Registrierungsprozess hier)
        $stmtInsertUser = $pdo->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, phone_number) VALUES (:username, :email, :password_hash, :first_name, :last_name, :phone_number)");
        // Generiere einen einfachen Hash für Guest-User oder verwende Platzhalter
        $dummyPasswordHash = password_hash(uniqid(), PASSWORD_DEFAULT);
        $stmtInsertUser->execute([
            ':username' => $email, // Oder einen generischen Usernamen
            ':email' => $email,
            ':password_hash' => $dummyPasswordHash,
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':phone_number' => $phone
        ]);
        $userId = $pdo->lastInsertId();
    }

    // 3. Warenkorb-Gesamtbetrag serverseitig neu berechnen und Lagerbestand prüfen
    $calculatedTotalAmount = 0;
    $orderItems = [];
    $productsToUpdateStock = [];

    // Kopie des Warenkorbs für die Success-Seite, da $_SESSION['cart'] gleich geleert wird
    $cart_snapshot_for_success_page = $_SESSION['cart'];

    foreach ($cart_snapshot_for_success_page as $productId => $item) {
        $stmtProduct = $pdo->prepare("SELECT product_id, name, description, price, stock_quantity FROM products WHERE product_id = :product_id AND is_active = TRUE");
        $stmtProduct->execute([':product_id' => $productId]);
        $dbProduct = $stmtProduct->fetch();

        if (!$dbProduct) {
            throw new Exception("Produkt '{$item['name']}' nicht gefunden oder nicht mehr verfügbar.");
        }

        if ($item['quantity'] > $dbProduct['stock_quantity']) {
            throw new Exception("Nicht genügend Lagerbestand für Produkt '{$dbProduct['name']}'. Verfügbar: {$dbProduct['stock_quantity']}, Bestellt: {$item['quantity']}.");
        }

        $calculatedTotalAmount += $dbProduct['price'] * $item['quantity'];
        $orderItems[] = [
            'product_id' => $productId,
            'quantity' => $item['quantity'],
            'price_at_order' => $dbProduct['price'],
            'name' => $dbProduct['name'] // Produktname für die E-Mail
        ];
        $productsToUpdateStock[$productId] = $dbProduct['stock_quantity'] - $item['quantity'];
    }

    // Optional: Überprüfung, ob der vom Client übermittelte Gesamtbetrag dem serverseitig berechneten entspricht
    // Eine geringe Toleranz für Floating-Point-Ungenauigkeiten könnte hier sinnvoll sein.
    if (abs($calculatedTotalAmount - $cartTotalFromClient) > 0.01) {
        error_log("Client-Total ($cartTotalFromClient) does not match server-total ($calculatedTotalAmount) for user $userId. Possible manipulation or rounding error.");
        // Entscheiden, ob die Bestellung abgebrochen oder der server-berechnete Betrag verwendet wird.
        // Für dieses Beispiel verwenden wir den server-berechneten Betrag.
    }


    // 4. Bestellung in die 'orders'-Tabelle einfügen
    $stmtOrder = $pdo->prepare("INSERT INTO orders (user_id, pickup_date_id, total_amount, status, payment_method) VALUES (:user_id, :pickup_date_id, :total_amount, 'pending', :payment_method)");
    $stmtOrder->execute([
        ':user_id' => $userId,
        ':pickup_date_id' => $pickupDateId,
        ':total_amount' => $calculatedTotalAmount,
        ':payment_method' => $paymentMethod
    ]);
    $orderId = $pdo->lastInsertId();

    // 5. Artikel in 'order_items' einfügen und Lagerbestand aktualisieren
    $stmtOrderItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price_at_order) VALUES (:order_id, :product_id, :quantity, :price_at_order)");
    $stmtUpdateStock = $pdo->prepare("UPDATE products SET stock_quantity = :new_stock WHERE product_id = :product_id");

    foreach ($orderItems as $item) {
        $stmtOrderItem->execute([
            ':order_id' => $orderId,
            ':product_id' => $item['product_id'],
            ':quantity' => $item['quantity'],
            ':price_at_order' => $item['price_at_order']
        ]);
        // Lagerbestand aktualisieren
        $stmtUpdateStock->execute([
            ':new_stock' => $productsToUpdateStock[$item['product_id']],
            ':product_id' => $item['product_id']
        ]);
    }

    // 6. Zahlungsinformationen in 'payments' einfügen
    $paymentStatus = ($paymentMethod === 'cash') ? 'pending' : 'pending'; // Für Überweisung auch 'pending' bis zum Geldeingang
    $transactionId = null;

    if ($paymentMethod === 'bank_transfer') {
        // Generiere Transaktions-ID: Nachname + 4-stellige Zufallszahl
        $randomNumber = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $transactionId = strtoupper(substr($lastName, 0, 5)) . '-' . $randomNumber; // Erste 5 Buchstaben des Nachnamens + Zufallszahl
    }


    $stmtPayment = $pdo->prepare("INSERT INTO payments (order_id, amount, payment_method, transaction_id, status, notes) VALUES (:order_id, :amount, :payment_method, :transaction_id, :status, :notes)");
    $stmtPayment->execute([
        ':order_id' => $orderId,
        ':amount' => $calculatedTotalAmount,
        ':payment_method' => $paymentMethod,
        ':transaction_id' => $transactionId,
        ':status' => $paymentStatus,
        ':notes' => ($paymentMethod === 'cash') ? 'Barzahlung bei Abholung' : 'Überweisung erwartet'
    ]);

    // Transaktion committen
    $pdo->commit();

    // WARNUNG: $_SESSION['cart'] MUSS nach dem Committen der DB und VOR dem setzen der Success-Seite Daten geleert werden!
    // Andernfalls würde die Success-Seite den Warenkorb wieder befüllen, wenn die Session vor dem Redirect leert.
    // Aber für unser Frontend wird der Warenkorb ja per reload gelehrt. Wichtig ist, DASS er geleert wird.
    $_SESSION['cart'] = [];


    // --- Daten für die Success-Seite in der Session speichern (NEU) ---
    $_SESSION['last_order_id'] = $orderId;
    $_SESSION['last_order_details'] = [
        'order_id' => $orderId,
        'user_id' => $userId,
        'total_amount' => $calculatedTotalAmount,
        'order_date' => date('Y-m-d H:i:s'), // Aktuelles Datum und Uhrzeit
        'payment_method' => $paymentMethod, // Muss aus dem Formular kommen
        'items' => $orderItems, // Bereits definierte Items
    ];
    $_SESSION['last_pickup_info'] = $pickupDateInfo; // Aus der Datenbank abgerufen
    $_SESSION['last_payment_info'] = [
        'payment_method' => $paymentMethod,
        'transaction_id' => $transactionId,
        'amount' => $calculatedTotalAmount, // Hier die Gesamtsumme der Bestellung verwenden
    ];
    $_SESSION['customer_info'] = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'phone' => $phone
    ];
    // --- Ende der Success-Seiten-Daten (NEU) ---

    // 7. Bestätigungs-E-Mail senden
    $emailSubject = "Ihre Bestellung Nr. {$orderId} bei " . MAIL_FROM_NAME;
    $emailBody = "
        <p>Hallo {$firstName} {$lastName},</p>
        <p>Vielen Dank für Ihre Bestellung! Ihre Bestellung mit der Nummer <strong>" . htmlspecialchars($orderId) . "</strong> wurde erfolgreich aufgenommen.</p>
        <p><strong>Bestelldetails:</strong></p>
        <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px;'>
            <thead>
                <tr>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Produkt</th>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: right;'>Menge</th>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: right;'>Einzelpreis</th>
                    <th style='border: 1px solid #ddd; padding: 8px; text-align: right;'>Gesamt</th>
                </tr>
            </thead>
            <tbody>";
    foreach ($orderItems as $item) {
        $emailBody .= "
                <tr>
                    <td style='border: 1px solid #ddd; padding: 8px; text-align: left;'>".htmlspecialchars($item['name'])."</td>
                    <td style='border: 1px solid #ddd; padding: 8px; text-align: right;'>{$item['quantity']}</td>
                    <td style='border: 1px solid #ddd; padding: 8px; text-align: right;'>".formatEuroCurrency($item['price_at_order'])."</td>
                    <td style='border: 1px solid #ddd; padding: 8px; text-align: right;'>".formatEuroCurrency($item['quantity'] * $item['price_at_order'])."</td>
                </tr>";
    }
    $emailBody .= "
            </tbody>
            <tfoot>
                <tr>
                    <td colspan='3' style='border: 1px solid #ddd; padding: 8px; text-align: right; font-weight: bold;'>Gesamtsumme:</td>
                    <td style='border: 1px solid #ddd; padding: 8px; text-align: right; font-weight: bold;'>".formatEuroCurrency($calculatedTotalAmount)."</td>
                </tr>
            </tfoot>
        </table>
        <p><strong>Zahlungsmethode:</strong> ";
    if ($paymentMethod === 'bank_transfer') {
        $emailBody .= "Überweisung. Bitte überweisen Sie den Betrag von <strong>" . formatEuroCurrency($calculatedTotalAmount) . "</strong> auf unser Konto. <br>
                       Verwendungszweck: <strong>" . htmlspecialchars($transactionId) . "</strong>.<br>
                       Kontoinhaber: [Ihr Name/Firmenname]<br>
                       IBAN: [Ihre IBAN]<br>
                       BIC: [Ihre BIC]";
    } else {
        $emailBody .= "Barzahlung bei Abholung.";
    }
    $emailBody .= "</p>
        <p><strong>Abholung:</strong> Ihre Bestellung ist zur Abholung bereit am <strong>" . (new DateTime($pickupDateInfo['pickup_datetime']))->format('d.m.Y H:i') . " Uhr</strong> an folgender Adresse: <strong>" . htmlspecialchars($pickupDateInfo['location']) . "</strong>.</p>
        <p>Wir freuen uns auf Sie!</p>
        <p>Mit freundlichen Grüssen,<br>Ihr Team von " . MAIL_FROM_NAME . "</p>";

    $emailSent = sendAppEmail($email, $emailSubject, $emailBody, $orderId);

    // $_SESSION['checkout_success_message'] = "Ihre Bestellung Nr. {$orderId} wurde erfolgreich aufgegeben. Eine Bestätigungs-E-Mail wurde an Ihre Adresse gesendet."; // Diese Meldung ist redundant wenn der Success-Inhalt alle Infos hat
    $_SESSION['checkout_email_status_message'] = "";
    if ($emailSent !== true) {
        error_log("Fehler beim Senden der Bestätigungs-E-Mail für Bestellung {$orderId} an {$email}: " . $emailSent);
        $_SESSION['checkout_email_status_message'] = "ACHTUNG: Die Bestätigungs-E-Mail konnte jedoch nicht gesendet werden. Bitte überprüfen Sie Ihren Spam-Ordner oder kontaktieren Sie uns unter info@früch.de.";
    }


    $response['success'] = true;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Bestellfehler: " . $e->getMessage());
    $response['message'] = "Fehler bei der Bestellabwicklung: " . $e->getMessage();
}

echo json_encode($response);