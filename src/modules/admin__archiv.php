<?php
// modules/admin.php

// Session starten, falls nicht bereits geschehen
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Benötigte Konfiguration und Funktionen laden
require_once ROOT_PATH . 'config/sensitive_config.php';
require_once ROOT_PATH . 'include/db.php'; // Für getDbConnection() und $pdo
require_once ROOT_PATH . 'include/email.php'; // Für sendAppEmail() und formatEuroCurrency (oder besser in Helpers auslagern)

// Helper-Funktion für Euro-Formatierung (muss hier verfügbar sein)
if (!function_exists('formatEuroCurrency')) {
    function formatEuroCurrency(float $amount): string {
        if (fmod($amount, 1.0) == 0) {
            return number_format($amount, 0, ',', '.') . ' €';
        } else {
            return number_format($amount, 2, ',', '.') . ' €';
        }
    }
}

global $pdo; // Sicherstellen, dass $pdo verfügbar ist
if (!isset($pdo)) {
    $pdo = getDbConnection();
}

// --- GLOBALE VARIABLEN ---
$isAdminLoggedIn = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);
$loginError = '';
$currentSection = $_GET['section'] ?? 'dashboard';
$actionStatus = $_GET['status'] ?? ''; // Für Erfolgs-/Fehlermeldungen nach Aktionen
$actionMessage = $_GET['msg'] ?? ''; // Zusätzliche Nachricht bei Fehlern

// --- VORVERARBEITUNG VON POST- UND GET-ANFRAGEN (VOR JEDER HTML-AUSGABE) ---

// 1. Logout-Logik (GET-Anfrage)
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: /admin'); // Redirect zur Login-Seite
    exit();
}

// 2. Login-Logik (POST-Anfrage)
if (isset($_POST['admin_password']) && !$isAdminLoggedIn) {
    if (password_verify($_POST['admin_password'], ADMIN_PASSWORD_HASH)) {
        $_SESSION['admin_logged_in'] = true;
        // Redirect nach erfolgreichem Login, um POST-Daten zu vermeiden
        header('Location: /admin');
        exit();
    } else {
        $loginError = 'Falsches Passwort.';
    }
}

// 3. Verarbeite Admin-Aktionen (Hinzufügen, Bearbeiten, Löschen) NACHDEM der Login überprüft wurde
if ($isAdminLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_GET['id'] ?? 0); // Allgemeine ID für Aktionen
    $action = $_GET['action'] ?? '';

    try {
        switch ($currentSection) {
            case 'orders':
                if (isset($_POST['update_order']) && $id > 0) {
                    $newStatus = $_POST['status'];

                    // Alten Status abrufen
                    $stmtOldStatus = $pdo->prepare("SELECT o.status, u.email, u.first_name, u.last_name FROM orders o JOIN users u ON o.user_id = u.user_id WHERE o.order_id = :id");
                    $stmtOldStatus->execute([':id' => $id]);
                    $oldOrderInfo = $stmtOldStatus->fetch();

                    if ($oldOrderInfo && $oldOrderInfo['status'] !== $newStatus) { // Nur senden, wenn Status sich wirklich ändert
                        $stmtUpdate = $pdo->prepare("UPDATE orders SET status = :status WHERE order_id = :id");
                        $stmtUpdate->execute([':status' => $newStatus, ':id' => $id]);

                        // E-Mail an den Kunden senden
                        $customerEmail = $oldOrderInfo['email'];
                        $customerName = $oldOrderInfo['first_name'] . ' ' . $oldOrderInfo['last_name'];
                        $emailSubject = "Status Ihrer Bestellung #{$id} bei " . MAIL_FROM_NAME . " aktualisiert";
                        $emailBody = "
                            <p>Hallo {$customerName},</p>
                            <p>der Status Ihrer Bestellung mit der Nummer <strong>#{$id}</strong> wurde aktualisiert.</p>
                            <p>Alter Status: <strong>" . htmlspecialchars(ucfirst($oldOrderInfo['status'])) . "</strong></p>
                            <p>Neuer Status: <strong>" . htmlspecialchars(ucfirst($newStatus)) . "</strong></p>
                            <p>Sie können den aktuellen Status Ihrer Bestellungen jederzeit in Ihrem Kundenkonto einsehen.</p>
                            <p>Vielen Dank für Ihr Vertrauen!</p>
                            <p>Mit freundlichen Grüssen,<br>Ihr Team von " . MAIL_FROM_NAME . "</p>";

                        sendAppEmail($customerEmail, $emailSubject, $emailBody, $id);
                        header('Location: ?section=orders&status=success&msg=' . urlencode('Bestellstatus erfolgreich aktualisiert.'));
                    } else {
                        header('Location: ?section=orders&status=success&msg=' . urlencode('Bestellstatus war bereits aktuell. Keine Änderung vorgenommen.'));
                    }
                    exit();
                }
                break;

            case 'customers':
                if (isset($_POST['update_customer']) && $id > 0) {
                    $firstName = $_POST['first_name'];
                    $lastName = $_POST['last_name'];
                    $email = $_POST['email'];
                    $phone = $_POST['phone_number'];
                    $stmtUpdate = $pdo->prepare("UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email, phone_number = :phone_number, updated_at = CURRENT_TIMESTAMP WHERE user_id = :id");
                    $stmtUpdate->execute([
                        ':first_name' => $firstName,
                        ':last_name' => $lastName,
                        ':email' => $email,
                        ':phone_number' => $phone,
                        ':id' => $id
                    ]);
                    header('Location: ?section=customers&status=success');
                    exit();
                }
                break;

            case 'payments':
                if (isset($_POST['add_payment'])) {
                    $order_id = (int)$_POST['order_id'];
                    $payment_date = $_POST['payment_date'];
                    $amount = (float)$_POST['amount'];
                    $payment_method = $_POST['payment_method'];
                    $transaction_id = $_POST['transaction_id'] ?: null;
                    $status = $_POST['status'];
                    $notes = $_POST['notes'] ?: null;

                    // Überprüfen, ob die Bestell-ID existiert
                    $stmtOrderCheck = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE order_id = :order_id");
                    $stmtOrderCheck->execute([':order_id' => $order_id]);
                    if ($stmtOrderCheck->fetchColumn() == 0) {
                        throw new Exception("Bestell-ID existiert nicht.");
                    }

                    $stmt = $pdo->prepare("INSERT INTO payments (order_id, payment_date, amount, payment_method, transaction_id, status, notes) VALUES (:order_id, :payment_date, :amount, :payment_method, :transaction_id, :status, :notes)");
                    $stmt->execute([
                        ':order_id' => $order_id,
                        ':payment_date' => $payment_date,
                        ':amount' => $amount,
                        ':payment_method' => $payment_method,
                        ':transaction_id' => $transaction_id,
                        ':status' => $status,
                        ':notes' => $notes
                    ]);

                    // E-Mail an den Kunden senden, wenn Zahlung abgeschlossen
                    if ($status === 'completed') {
                        $stmtOrderUser = $pdo->prepare("SELECT u.email, u.first_name, u.last_name, o.total_amount FROM orders o JOIN users u ON o.user_id = u.user_id WHERE o.order_id = :order_id");
                        $stmtOrderUser->execute([':order_id' => $order_id]);
                        $orderUserInfo = $stmtOrderUser->fetch();

                        if ($orderUserInfo) {
                            $customerEmail = $orderUserInfo['email'];
                            $customerName = $orderUserInfo['first_name'] . ' ' . $orderUserInfo['last_name'];
                            $emailSubject = "Zahlung für Bestellung #{$order_id} bei " . MAIL_FROM_NAME . " eingegangen";
                            $emailBody = "
                                <p>Hallo {$customerName},</p>
                                <p>wir freuen uns, Ihnen mitteilen zu können, dass Ihre Zahlung von <strong>" . formatEuroCurrency($amount) . "</strong> für die Bestellung <strong>#{$order_id}</strong> erfolgreich bearbeitet und als 'Abgeschlossen' markiert wurde.</p>
                                <p>Gesamtbetrag der Bestellung: <strong>" . formatEuroCurrency($orderUserInfo['total_amount']) . "</strong></p>
                                <p>Wir bedanken uns für Ihre Bestellung!</p>
                                <p>Mit freundlichen Grüssen,<br>Ihr Team von " . MAIL_FROM_NAME . "</p>";

                            sendAppEmail($customerEmail, $emailSubject, $emailBody, $order_id);
                        }
                    }

                    // Redirect zurück zur Zahlungsliste oder zur Bestelldetailseite, wenn von dort gekommen
                    if (isset($_POST['return_to_order_id']) && (int)$_POST['return_to_order_id'] === $order_id) {
                         header('Location: ?section=orders&action=edit&id=' . $order_id . '&status=success&msg=' . urlencode('Zahlung erfolgreich hinzugefügt.'));
                    } else {
                        header('Location: ?section=payments&status=success');
                    }
                    exit();
                } elseif (isset($_POST['update_payment']) && $id > 0) {
                    // Alten Zahlungsstatus abrufen und User-Info holen
                    $stmtOldPayment = $pdo->prepare("SELECT p.status, p.order_id, u.email, u.first_name, u.last_name, o.total_amount FROM payments p JOIN orders o ON p.order_id = o.order_id JOIN users u ON o.user_id = u.user_id WHERE p.payment_id = :id");
                    $stmtOldPayment->execute([':id' => $id]);
                    $oldPaymentInfo = $stmtOldPayment->fetch();

                    $payment_date = $_POST['payment_date'];
                    $amount = (float)$_POST['amount'];
                    $payment_method = $_POST['payment_method'];
                    $transaction_id = $_POST['transaction_id'] ?: null;
                    $status = $_POST['status'];
                    $notes = $_POST['notes'] ?: null;

                    $stmtUpdate = $pdo->prepare("UPDATE payments SET payment_date = :payment_date, amount = :amount, payment_method = :payment_method, transaction_id = :transaction_id, status = :status, notes = :notes WHERE payment_id = :id");
                    $stmtUpdate->execute([
                        ':payment_date' => $payment_date,
                        ':amount' => $amount,
                        ':payment_method' => $payment_method,
                        ':transaction_id' => $transaction_id,
                        ':status' => $status,
                        ':notes' => $notes,
                        ':id' => $id
                    ]);

                    // E-Mail an den Kunden senden, wenn der Status sich geändert hat (besonders wichtig bei 'completed')
                    if ($oldPaymentInfo && $oldPaymentInfo['status'] !== $status) {
                        $customerEmail = $oldPaymentInfo['email'];
                        $customerName = $oldPaymentInfo['first_name'] . ' ' . $oldPaymentInfo['last_name'];
                        $orderId = $oldPaymentInfo['order_id'];
                        $emailSubject = "Status Ihrer Zahlung #{$id} für Bestellung #{$orderId} bei " . MAIL_FROM_NAME . " aktualisiert";
                        $emailBody = "
                            <p>Hallo {$customerName},</p>
                            <p>der Status Ihrer Zahlung mit der ID <strong>#{$id}</strong> für die Bestellung <strong>#{$orderId}</strong> wurde aktualisiert.</p>
                            <p>Alter Status: <strong>" . htmlspecialchars(ucfirst($oldPaymentInfo['status'])) . "</strong></p>
                            <p>Neuer Status: <strong>" . htmlspecialchars(ucfirst($status)) . "</strong></p>
                            <p>Betrag der Zahlung: <strong>" . formatEuroCurrency($amount) . "</strong></p>
                            <p>Bei Rückfragen stehen wir Ihnen gerne zur Verfügung.</p>
                            <p>Mit freundlichen Grüssen,<br>Ihr Team von " . MAIL_FROM_NAME . "</p>";

                        sendAppEmail($customerEmail, $emailSubject, $emailBody, $orderId);
                    }

                    header('Location: ?section=payments&status=success');
                    exit();
                } elseif (isset($_POST['delete_payment']) && $id > 0) {
                     $stmt = $pdo->prepare("DELETE FROM payments WHERE payment_id = :id");
                     $stmt->execute([':id' => $id]);
                     // Optional: E-Mail senden, dass Zahlung storniert/gelöscht wurde.
                     header('Location: ?section=payments&status=success');
                     exit();
                }
                break;

            case 'products':
                if (isset($_POST['add_product'])) {
                    $stmt = $pdo->prepare("INSERT INTO products (name, description, price, stock_quantity, image_url, category_id, is_active) VALUES (:name, :description, :price, :stock_quantity, :image_url, :category_id, :is_active)");
                    $stmt->execute([
                        ':name' => $_POST['name'],
                        ':description' => $_POST['description'],
                        ':price' => $_POST['price'],
                        ':stock_quantity' => $_POST['stock_quantity'],
                        ':image_url' => $_POST['image_url'],
                        ':category_id' => $_POST['category_id'],
                        ':is_active' => isset($_POST['is_active']) ? 1 : 0
                    ]);
                    header('Location: ?section=products&status=success');
                    exit();
                } elseif (isset($_POST['update_product']) && $id > 0) {
                    $stmt = $pdo->prepare("UPDATE products SET name = :name, description = :description, price = :price, stock_quantity = :stock_quantity, image_url = :image_url, category_id = :category_id, is_active = :is_active, updated_at = CURRENT_TIMESTAMP WHERE product_id = :id");
                    $stmt->execute([
                        ':name' => $_POST['name'],
                        ':description' => $_POST['description'],
                        ':price' => $_POST['price'],
                        ':stock_quantity' => $_POST['stock_quantity'],
                        ':image_url' => $_POST['image_url'],
                        ':category_id' => $_POST['category_id'],
                        ':is_active' => isset($_POST['is_active']) ? 1 : 0,
                        ':id' => $id
                    ]);
                    header('Location: ?section=products&status=success');
                    exit();
                } elseif (isset($_POST['delete_product']) && $id > 0) {
                     $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = :id");
                     $stmt->execute([':id' => $id]);
                     header('Location: ?section=products&status=success');
                     exit();
                }
                break;

            case 'categories':
                if (isset($_POST['add_category'])) {
                    $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (:name, :description)");
                    $stmt->execute([
                        ':name' => $_POST['name'],
                        ':description' => $_POST['description']
                    ]);
                    header('Location: ?section=categories&status=success');
                    exit();
                } elseif (isset($_POST['update_category']) && $id > 0) {
                    $stmt = $pdo->prepare("UPDATE categories SET name = :name, description = :description, updated_at = CURRENT_TIMESTAMP WHERE category_id = :id");
                    $stmt->execute([
                        ':name' => $_POST['name'],
                        ':description' => $_POST['description'],
                        ':id' => $id
                    ]);
                    header('Location: ?section=categories&status=success');
                    exit();
                } elseif (isset($_POST['delete_category']) && $id > 0) {
                    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = :id");
                    $stmtCheck->execute([':id' => $id]);
                    if ($stmtCheck->fetchColumn() > 0) {
                        throw new Exception("Kategorie kann nicht gelöscht werden, da noch Produkte zugeordnet sind.");
                    }
                     $stmt = $pdo->prepare("DELETE FROM categories WHERE category_id = :id");
                     $stmt->execute([':id' => $id]);
                     header('Location: ?section=categories&status=success');
                     exit();
                }
                break;

            case 'pickup_dates':
                if (isset($_POST['add_pickup_date'])) {
                    $stmt = $pdo->prepare("INSERT INTO pickup_dates (pickup_datetime, location, is_active, notes) VALUES (:pickup_datetime, :location, :is_active, :notes)");
                    $stmt->execute([
                        ':pickup_datetime' => $_POST['pickup_datetime'],
                        ':location' => $_POST['location'],
                        ':is_active' => isset($_POST['is_active']) ? 1 : 0,
                        ':notes' => $_POST['notes']
                    ]);
                    header('Location: ?section=pickup_dates&status=success');
                    exit();
                } elseif (isset($_POST['update_pickup_date']) && $id > 0) {
                    $stmt = $pdo->prepare("UPDATE pickup_dates SET pickup_datetime = :pickup_datetime, location = :location, is_active = :is_active, notes = :notes, updated_at = CURRENT_TIMESTAMP WHERE pickup_date_id = :id");
                    $stmt->execute([
                        ':pickup_datetime' => $_POST['pickup_datetime'],
                        ':location' => $_POST['location'],
                        ':is_active' => isset($_POST['is_active']) ? 1 : 0,
                        ':notes' => $_POST['notes'],
                        ':id' => $id
                    ]);
                    header('Location: ?section=pickup_dates&status=success');
                    exit();
                } elseif (isset($_POST['delete_pickup_date']) && $id > 0) {
                     $stmt = $pdo->prepare("DELETE FROM pickup_dates WHERE pickup_date_id = :id");
                     $stmt->execute([':id' => $id]);
                     header('Location: ?section=pickup_dates&status=success');
                     exit();
                }
                break;
        }
    } catch (Exception $e) {
        error_log("Admin-Aktion Fehler: " . $e->getMessage());
        header('Location: ?section=' . $currentSection . '&status=error&msg=' . urlencode($e->getMessage()));
        exit();
    }
}

// --- HTML-AUSGABE STARTET HIER ---

// Wenn nicht eingeloggt, zeige Login-Formular
if (!$isAdminLoggedIn) {
    ?>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .login-container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); text-align: center; width: 350px; }
        .login-container h2 { margin-bottom: 25px; color: #333; }
        .login-container input[type="password"] { width: calc(100% - 24px); padding: 12px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 6px; font-size: 1em; }
        .login-container button { background-color: #4285f4; color: white; padding: 12px 25px; border: none; border-radius: 6px; cursor: pointer; font-size: 1em; transition: background-color 0.2s ease; }
        .login-container button:hover { background-color: #357ae8; }
        .login-container .error { color: #e44d26; margin-bottom: 15px; }
    </style>
    <div class="login-container">
        <h2>Admin Login</h2>
        <?php if ($loginError): ?>
            <p class="error"><?php echo htmlspecialchars($loginError); ?></p>
        <?php endif; ?>
        <form method="POST">
            <input type="password" name="admin_password" placeholder="Passwort" required>
            <button type="submit">Login</button>
        </form>
    </div>
    <?php
    exit(); // Beende Skript, wenn nicht eingeloggt
}

// CSS für die Admin-Seite (nur anzeigen, wenn eingeloggt)
// Dieses CSS wird durch index.php oder template/header.php über assets/main.css geladen
// HINWEIS: Bei der Umstellung auf Variablen in main.css könnte dieser inline-style Block entfernt werden.
?>

<div class="admin-container">
    <div class="admin-sidebar">
        <h2>Admin Panel</h2>
        <ul>
            <li><a href="?section=dashboard" class="<?php echo ($currentSection == 'dashboard' ? 'active' : ''); ?>">Dashboard</a></li>
            <li><a href="?section=orders" class="<?php echo ($currentSection == 'orders' ? 'active' : ''); ?>">Bestellungen</a></li>
            <li><a href="?section=customers" class="<?php echo ($currentSection == 'customers' ? 'active' : ''); ?>">Kunden</a></li>
            <li><a href="?section=payments" class="<?php echo ($currentSection == 'payments' ? 'active' : ''); ?>">Zahlungen</a></li>
            <li><a href="?section=products" class="<?php echo ($currentSection == 'products' ? 'active' : ''); ?>">Produkte</a></li>
            <li><a href="?section=categories" class="<?php echo ($currentSection == 'categories' ? 'active' : ''); ?>">Kategorien</a></li>
            <li><a href="?section=pickup_dates" class="<?php echo ($currentSection == 'pickup_dates' ? 'active' : ''); ?>">Abholtermine</a></li>
            <li><a href="?section=email_logs" class="<?php echo ($currentSection == 'email_logs' ? 'active' : ''); ?>">E-Mail Logs</a></li>
            <li><a href="?action=logout">Logout</a></li>
        </ul>
    </div>
    <div class="admin-content">
        <h1><?php echo ucfirst(str_replace('_', ' ', $currentSection)); ?> Verwaltung</h1>

        <?php if ($actionStatus === 'success'): ?>
            <div class="alert success"><?php echo htmlspecialchars($actionMessage ?: 'Aktion erfolgreich ausgeführt.'); ?></div>
        <?php elseif ($actionStatus === 'error'): ?>
            <div class="alert error"><?php echo htmlspecialchars($actionMessage ?: 'Fehler bei der Aktion.'); ?></div>
        <?php endif; ?>

        <?php
        switch ($currentSection) {
            case 'dashboard':
                // Dashboard-Statistiken abrufen
                $totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
                $pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
                $totalCustomers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                $totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

                // Umsatzstatistiken
                $totalExpectedIncome = $pdo->query("SELECT SUM(total_amount) FROM orders")->fetchColumn();
                // Summe aller abgeschlossenen Zahlungen
                $totalReceivedIncome = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'completed'")->fetchColumn();
                $totalOutstanding = $totalExpectedIncome - $totalReceivedIncome;

                ?>
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <h3>Gesamtbestellungen</h3>
                        <p><?php echo $totalOrders; ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Offene Bestellungen</h3>
                        <p><?php echo $pendingOrders; ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Registrierte Kunden</h3>
                        <p><?php echo $totalCustomers; ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Verfügbare Produkte</h3>
                        <p><?php echo $totalProducts; ?></p>
                    </div>
                </div>

                <h3>Finanzübersicht</h3>
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <h3>Erwarteter Umsatz</h3>
                        <p><?php echo formatEuroCurrency($totalExpectedIncome); ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Eingegangener Umsatz</h3>
                        <p><?php echo formatEuroCurrency($totalReceivedIncome); ?></p>
                    </div>
                    <div class="stat-card <?php echo ($totalOutstanding > 0 ? 'income-negative' : 'income-positive'); ?>">
                        <h3>Offener Betrag</h3>
                        <p><?php echo formatEuroCurrency($totalOutstanding); ?></p>
                    </div>
                </div>

                <h3>Umsatzentwicklung (Platzhalter für Grafik)</h3>
                <div class="chart-container">
                    <canvas id="incomeChart" style="display: none;"></canvas>
                    <p id="chartFallback">Lade Grafik...</p>
                    <pre style="display: none;" id="chartData">
                        <?php
                        // Beispiel-Daten für eine Grafik (würden normalerweise komplexer abgefragt)
                        $monthlySales = $pdo->query("
                            SELECT
                                DATE_FORMAT(order_date, '%Y-%m') as month,
                                SUM(total_amount) as total_ordered
                            FROM orders
                            GROUP BY month
                            ORDER BY month ASC
                            LIMIT 12 -- Letzte 12 Monate
                        ")->fetchAll();

                        $monthlyPayments = $pdo->query("
                            SELECT
                                DATE_FORMAT(payment_date, '%Y-%m') as month,
                                SUM(amount) as total_paid
                            FROM payments
                            WHERE status = 'completed'
                            GROUP BY month
                            ORDER BY month ASC
                            LIMIT 12
                        ")->fetchAll();

                        $chartLabels = [];
                        $chartOrderedData = [];
                        $chartPaidData = [];

                        // Monate der letzten 12 Monate generieren
                        $period = new DatePeriod(
                            new DateTime('-11 months first day of this month'),
                            new DateInterval('P1M'),
                            new DateTime('first day of next month')
                        );

                        $allMonths = [];
                        foreach ($period as $dt) {
                            $allMonths[$dt->format('Y-m')] = 0;
                        }

                        // Daten zusammenführen
                        $mergedSales = $allMonths;
                        foreach ($monthlySales as $sale) {
                            $mergedSales[$sale['month']] = (float)$sale['total_ordered'];
                        }
                        $mergedPayments = $allMonths;
                        foreach ($monthlyPayments as $payment) {
                            $mergedPayments[$payment['month']] = (float)$payment['total_paid'];
                        }

                        foreach ($mergedSales as $month => $total) {
                            $chartLabels[] = (new DateTime($month . '-01'))->format('M Y');
                            $chartOrderedData[] = $total;
                            $chartPaidData[] = $mergedPayments[$month] ?? 0; // Sicherstellen, dass ein Wert existiert
                        }

                        echo json_encode([
                            'labels' => $chartLabels,
                            'ordered' => $chartOrderedData,
                            'paid' => $chartPaidData
                        ]);
                        ?>
                    </pre>
                    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const chartDataElement = document.getElementById('chartData');
                            const chartFallback = document.getElementById('chartFallback');
                            const canvas = document.getElementById('incomeChart');

                            if (chartDataElement && canvas) {
                                try {
                                    const chartConfig = JSON.parse(chartDataElement.textContent);

                                    if (chartConfig.labels && chartConfig.labels.length > 0) {
                                        chartFallback.style.display = 'none';
                                        canvas.style.display = 'block';

                                        const ctx = canvas.getContext('2d');
                                        new Chart(ctx, {
                                            type: 'bar',
                                            data: {
                                                labels: chartConfig.labels,
                                                datasets: [
                                                    {
                                                        label: 'Erwarteter Umsatz',
                                                        data: chartConfig.ordered,
                                                        backgroundColor: 'rgba(52, 104, 192, 0.7)', // var(--color-primary)
                                                        borderColor: 'rgba(52, 104, 192, 1)',
                                                        borderWidth: 1
                                                    },
                                                    {
                                                        label: 'Eingegangener Umsatz',
                                                        data: chartConfig.paid,
                                                        backgroundColor: 'rgba(0, 191, 99, 0.7)', // var(--color-secondary)
                                                        borderColor: 'rgba(0, 191, 99, 1)',
                                                        borderWidth: 1
                                                    }
                                                ]
                                            },
                                            options: {
                                                responsive: true,
                                                maintainAspectRatio: false,
                                                scales: {
                                                    y: {
                                                        beginAtZero: true,
                                                        title: {
                                                            display: true,
                                                            text: 'Betrag (€)'
                                                        }
                                                    },
                                                    x: {
                                                        title: {
                                                            display: true,
                                                            text: 'Monat'
                                                        }
                                                    }
                                                }
                                            }
                                        });
                                    } else {
                                        chartFallback.textContent = 'Keine Umsatzdaten für die Darstellung verfügbar.';
                                    }
                                } catch (e) {
                                    console.error("Fehler beim Parsen der Chart-Daten oder Initialisieren von Chart.js:", e);
                                    chartFallback.textContent = 'Fehler beim Laden der Grafikdaten.';
                                }
                            } else {
                                chartFallback.textContent = 'Chart-Container oder Daten nicht gefunden.';
                            }
                        });
                    </script>
                </div>
                <?php
                break;

            case 'orders':
                $orderIdToEdit = (int)($_GET['id'] ?? 0);
                $action = $_GET['action'] ?? '';

                if ($action === 'edit' && $orderIdToEdit > 0) {
                    $stmtEdit = $pdo->prepare("SELECT o.*, u.first_name, u.last_name, u.email, u.phone_number, pd.pickup_datetime, pd.location FROM orders o JOIN users u ON o.user_id = u.user_id LEFT JOIN pickup_dates pd ON o.pickup_date_id = pd.pickup_date_id WHERE o.order_id = :id");
                    $stmtEdit->execute([':id' => $orderIdToEdit]);
                    $orderToEdit = $stmtEdit->fetch();

                    if (!$orderToEdit) {
                         echo '<p class="alert error">Bestellung nicht gefunden.</p>';
                         break;
                    }

                    // Bestelldetails abrufen
                    $stmtItems = $pdo->prepare("SELECT oi.*, p.name as product_name FROM order_items oi JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = :order_id");
                    $stmtItems->execute([':order_id' => $orderIdToEdit]);
                    $orderItems = $stmtItems->fetchAll();

                    // Zahlungen für diese Bestellung abrufen
                    $stmtPayments = $pdo->prepare("SELECT * FROM payments WHERE order_id = :order_id ORDER BY payment_date ASC");
                    $stmtPayments->execute([':order_id' => $orderIdToEdit]);
                    $orderPayments = $stmtPayments->fetchAll();

                    $totalPaid = 0;
                    foreach($orderPayments as $payment) {
                        if ($payment['status'] === 'completed') { // Nur abgeschlossene Zahlungen zählen
                            $totalPaid += $payment['amount'];
                        }
                    }
                    $outstandingAmount = $orderToEdit['total_amount'] - $totalPaid;

                    ?>
                    <div class="admin-form-container">
                        <h3>Bestellung #<?php echo htmlspecialchars($orderToEdit['order_id']); ?> Details</h3>
                        <p><strong>Kunde:</strong> <?php echo htmlspecialchars($orderToEdit['first_name'] . ' ' . $orderToEdit['last_name']); ?> (<?php echo htmlspecialchars($orderToEdit['email']); ?>)</p>
                        <p><strong>Gesamtbestellwert:</strong> <?php echo formatEuroCurrency($orderToEdit['total_amount']); ?></p>
                        <p><strong>Gesamt bezahlt:</strong> <?php echo formatEuroCurrency($totalPaid); ?></p>
                        <p><strong>Offener Betrag:</strong> <span style="color: <?php echo ($outstandingAmount > 0 ? '#dc3545' : '#28a745'); ?>; font-weight: bold;"><?php echo formatEuroCurrency($outstandingAmount); ?></span></p>
                        <p><strong>Zahlungsmethode:</strong> <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $orderToEdit['payment_method']))); ?></p>
                        <p><strong>Abholung:</strong> <?php echo ($orderToEdit['pickup_datetime'] ? (new DateTime($orderToEdit['pickup_datetime']))->format('d.m.Y H:i') . ' Uhr - ' . htmlspecialchars($orderToEdit['location']) : 'N/A'); ?></p>

                        <h4>Bestellte Artikel:</h4>
                        <table>
                            <thead>
                                <tr>
                                    <th>Produkt</th>
                                    <th>Menge</th>
                                    <th>Einzelpreis bei Bestellung</th>
                                    <th>Summe</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderItems as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                        <td><?php echo formatEuroCurrency($item['price_at_order']); ?></td>
                                        <td><?php echo formatEuroCurrency($item['quantity'] * $item['price_at_order']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <h4>Zahlungen für diese Bestellung:</h4>
                        <?php if (empty($orderPayments)): ?>
                            <p>Keine Zahlungen für diese Bestellung erfasst.</p>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Betrag</th>
                                        <th>Datum</th>
                                        <th>Methode</th>
                                        <th>Transaktions ID</th>
                                        <th>Status</th>
                                        <th>Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orderPayments as $payment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($payment['payment_id']); ?></td>
                                            <td><?php echo formatEuroCurrency($payment['amount']); ?></td>
                                            <td><?php echo (new DateTime($payment['payment_date']))->format('d.m.Y H:i'); ?></td>
                                            <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $payment['payment_method']))); ?></td>
                                            <td><?php echo htmlspecialchars($payment['transaction_id'] ?: 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars(ucfirst($payment['status'])); ?></td>
                                            <td class="action-buttons">
                                                <a href="?section=payments&action=edit&id=<?php echo $payment['payment_id']; ?>" class="edit-btn">Bearbeiten</a>
                                                <form method="POST" action="?section=payments&action=delete&id=<?php echo $payment['payment_id']; ?>&return_to_order_id=<?php echo $orderToEdit['order_id']; ?>" onsubmit="return confirm('Sicher? Diese Zahlung löschen?');" style="display:inline;">
                                                    <button type="submit" name="delete_payment" class="delete-btn">Löschen</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                        <div class="action-buttons" style="margin-top: 15px;">
                            <a href="?section=payments&action=add&order_id=<?php echo $orderToEdit['order_id']; ?>" class="add-btn">Neue Zahlung hinzufügen</a>
                        </div>


                        <h4 style="margin-top: 30px;">Bestellstatus ändern:</h4>
                        <form method="POST" action="?section=orders&action=edit&id=<?php echo $orderToEdit['order_id']; ?>">
                            <div class="form-group">
                                <label for="order_status">Status:</label>
                                <select id="order_status" name="status" required>
                                    <option value="pending" <?php echo ($orderToEdit['status'] == 'pending' ? 'selected' : ''); ?>>Ausstehend</option>
                                    <option value="confirmed" <?php echo ($orderToEdit['status'] == 'confirmed' ? 'selected' : ''); ?>>Bestätigt</option>
                                    <option value="completed" <?php echo ($orderToEdit['status'] == 'completed' ? 'selected' : ''); ?>>Abgeschlossen</option>
                                    <option value="cancelled" <?php echo ($orderToEdit['status'] == 'cancelled' ? 'selected' : ''); ?>>Storniert</option>
                                </select>
                            </div>
                            <button type="submit" name="update_order">Status aktualisieren</button>
                        </form>
                    </div>
                    <?php
                } else {
                    $stmt = $pdo->query("SELECT o.*, u.first_name, u.last_name, pd.pickup_datetime, pd.location FROM orders o JOIN users u ON o.user_id = u.user_id LEFT JOIN pickup_dates pd ON o.pickup_date_id = pd.pickup_date_id ORDER BY o.order_date DESC");
                    $orders = $stmt->fetchAll();
                    ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Kunde</th>
                                <th>Datum</th>
                                <th>Betrag</th>
                                <th>Status</th>
                                <th>Zahlung</th>
                                <th>Abholung</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                                    <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                    <td><?php echo (new DateTime($order['order_date']))->format('d.m.Y H:i'); ?></td>
                                    <td><?php echo formatEuroCurrency($order['total_amount']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($order['status'])); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $order['payment_method']))); ?></td>
                                    <td><?php echo ($order['pickup_datetime'] ? (new DateTime($order['pickup_datetime']))->format('d.m.Y H:i') . ' - ' . htmlspecialchars($order['location']) : 'N/A'); ?></td>
                                    <td class="action-buttons">
                                        <a href="?section=orders&action=edit&id=<?php echo $order['order_id']; ?>" class="edit-btn">Details/Bearbeiten</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php
                }
                break;

            case 'customers':
                $customerIdToEdit = (int)($_GET['id'] ?? 0);
                $action = $_GET['action'] ?? '';

                if ($action === 'edit' && $customerIdToEdit > 0) {
                    $stmtEdit = $pdo->prepare("SELECT * FROM users WHERE user_id = :id");
                    $stmtEdit->execute([':id' => $customerIdToEdit]);
                    $customerToEdit = $stmtEdit->fetch();

                    if (!$customerToEdit) {
                        echo '<p class="alert error">Kunde nicht gefunden.</p>';
                        break;
                    }
                    ?>
                    <div class="admin-form-container">
                        <h3>Kunde #<?php echo htmlspecialchars($customerToEdit['user_id']); ?> bearbeiten</h3>
                        <form method="POST" action="?section=customers&action=edit&id=<?php echo $customerToEdit['user_id']; ?>">
                            <div class="form-group">
                                <label for="first_name">Vorname:</label>
                                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($customerToEdit['first_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Nachname:</label>
                                <input type="text" id="lastName" name="last_name" value="<?php echo htmlspecialchars($customerToEdit['last_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">E-Mail:</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($customerToEdit['email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="phone_number">Telefon:</label>
                                <input type="text" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($customerToEdit['phone_number']); ?>">
                            </div>
                            <button type="submit" name="update_customer">Kunden aktualisieren</button>
                        </form>
                    </div>
                    <?php
                } else {
                    $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
                    $customers = $stmt->fetchAll();
                    ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>E-Mail</th>
                                <th>Telefon</th>
                                <th>Registriert am</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($customer['user_id']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['phone_number'] ?: 'N/A'); ?></td>
                                    <td><?php echo (new DateTime($customer['created_at']))->format('d.m.Y H:i'); ?></td>
                                    <td class="action-buttons">
                                        <a href="?section=customers&action=edit&id=<?php echo $customer['user_id']; ?>" class="edit-btn">Bearbeiten</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php
                }
                break;

            case 'payments':
                $paymentIdToEdit = (int)($_GET['id'] ?? 0);
                $orderIdFromGet = (int)($_GET['order_id'] ?? 0); // Optional, wenn von Bestellseite verlinkt
                $action = $_GET['action'] ?? '';

                if ($action === 'add' || ($action === 'edit' && $paymentIdToEdit > 0)) {
                    $paymentToEdit = null;
                    if ($action === 'edit') {
                        $stmtEdit = $pdo->prepare("SELECT * FROM payments WHERE payment_id = :id");
                        $stmtEdit->execute([':id' => $paymentIdToEdit]);
                        $paymentToEdit = $stmtEdit->fetch();
                        if (!$paymentToEdit) {
                            echo '<p class="alert error">Zahlung nicht gefunden.</p>';
                            break;
                        }
                    }

                    // Hole Bestelldetails, falls eine order_id vorhanden ist (für Anzeige)
                    $orderInfo = null;
                    $targetOrderId = $orderIdFromGet ?: ($paymentToEdit['order_id'] ?? 0);
                    if ($targetOrderId > 0) {
                        $stmtOrder = $pdo->prepare("SELECT o.order_id, o.total_amount, u.first_name, u.last_name FROM orders o JOIN users u ON o.user_id = u.user_id WHERE o.order_id = :order_id");
                        $stmtOrder->execute([':order_id' => $targetOrderId]);
                        $orderInfo = $stmtOrder->fetch();
                    }

                    ?>
                    <div class="admin-form-container">
                        <h3><?php echo ($action === 'add' ? 'Neue Zahlung hinzufügen' : 'Zahlung #' . htmlspecialchars($paymentIdToEdit) . ' bearbeiten'); ?></h3>
                        <?php if ($orderInfo): ?>
                            <p><strong>Bestellung:</strong> #<?php echo htmlspecialchars($orderInfo['order_id']); ?> (Kunde: <?php echo htmlspecialchars($orderInfo['first_name'] . ' ' . $orderInfo['last_name']); ?>, Gesamt: <?php echo formatEuroCurrency($orderInfo['total_amount']); ?>)</p>
                            <!-- Hidden field to pass order_id back for redirect after successful add -->
                            <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($orderInfo['order_id']); ?>">
                            <input type="hidden" name="return_to_order_id" value="<?php echo htmlspecialchars($orderInfo['order_id']); ?>">
                        <?php elseif ($action === 'add'): // Wenn direkt auf Payments-Seite hinzugefügt wird ?>
                            <div class="form-group">
                                <label for="order_id">Bestell-ID:</label>
                                <input type="number" id="order_id" name="order_id" value="<?php echo htmlspecialchars($orderIdFromGet); ?>" required min="1">
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="?section=payments&action=<?php echo $action; ?><?php echo ($action === 'edit' ? '&id=' . $paymentIdToEdit : ''); ?>">
                            <?php if ($orderInfo): // Wenn von Bestellung aus hinzugefügt wird, die Order-ID übergeben ?>
                                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($orderInfo['order_id']); ?>">
                                <input type="hidden" name="return_to_order_id" value="<?php echo htmlspecialchars($orderInfo['order_id']); ?>">
                            <?php elseif ($action === 'add'): // Wenn direkt auf Payments-Seite hinzugefügt wird ?>
                                <div class="form-group">
                                    <label for="order_id">Bestell-ID:</label>
                                    <input type="number" id="order_id" name="order_id" value="<?php echo htmlspecialchars($orderIdFromGet); ?>" required min="1">
                                </div>
                            <?php endif; ?>

                            <div class="form-group">
                                <label for="payment_date">Zahlungsdatum & Uhrzeit:</label>
                                <input type="datetime-local" id="payment_date" name="payment_date" value="<?php echo (new DateTime($paymentToEdit['payment_date'] ?? 'now'))->format('Y-m-d\TH:i'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="amount">Betrag (€):</label>
                                <input type="number" id="amount" name="amount" step="0.01" min="0" value="<?php echo htmlspecialchars($paymentToEdit['amount'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="payment_method">Zahlungsmethode:</label>
                                <select id="payment_method" name="payment_method" required>
                                    <option value="bank_transfer" <?php echo (isset($paymentToEdit['payment_method']) && $paymentToEdit['payment_method'] == 'bank_transfer' ? 'selected' : ''); ?>>Überweisung</option>
                                    <option value="cash" <?php echo (isset($paymentToEdit['payment_method']) && $paymentToEdit['payment_method'] == 'cash' ? 'selected' : ''); ?>>Barzahlung</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="transaction_id">Transaktions ID (optional):</label>
                                <input type="text" id="transaction_id" name="transaction_id" value="<?php echo htmlspecialchars($paymentToEdit['transaction_id'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="payment_status">Status:</label>
                                <select id="payment_status" name="status" required>
                                    <option value="pending" <?php echo (isset($paymentToEdit['status']) && $paymentToEdit['status'] == 'pending' ? 'selected' : ''); ?>>Ausstehend</option>
                                    <option value="completed" <?php echo (isset($paymentToEdit['status']) && $paymentToEdit['status'] == 'completed' ? 'selected' : ''); ?>>Abgeschlossen</option>
                                    <option value="refunded" <?php echo (isset($paymentToEdit['status']) && $paymentToEdit['status'] == 'refunded' ? 'selected' : ''); ?>>Rückerstattet</option>
                                    <option value="failed" <?php echo (isset($paymentToEdit['status']) && $paymentToEdit['status'] == 'failed' ? 'selected' : ''); ?>>Fehlgeschlagen</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="notes">Notizen:</label>
                                <textarea id="notes" name="notes"><?php echo htmlspecialchars($paymentToEdit['notes'] ?? ''); ?></textarea>
                            </div>
                            <button type="submit" name="<?php echo ($action === 'add' ? 'add_payment' : 'update_payment'); ?>"><?php echo ($action === 'add' ? 'Zahlung hinzufügen' : 'Zahlung aktualisieren'); ?></button>
                        </form>
                    </div>
                    <?php
                } else {
                    $stmt = $pdo->query("SELECT p.*, o.order_id, u.first_name, u.last_name FROM payments p JOIN orders o ON p.order_id = o.order_id JOIN users u ON o.user_id = u.user_id ORDER BY p.payment_date DESC");
                    $payments = $stmt->fetchAll();
                    ?>
                    <div class="action-buttons">
                        <a href="?section=payments&action=add" class="add-btn">Neue Zahlung hinzufügen</a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Bestellung ID</th>
                                <th>Kunde</th>
                                <th>Datum</th>
                                <th>Betrag</th>
                                <th>Methode</th>
                                <th>Transaktions ID</th>
                                <th>Status</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['payment_id']); ?></td>
                                    <td><a href="?section=orders&action=edit&id=<?php echo htmlspecialchars($payment['order_id']); ?>"><?php echo htmlspecialchars($payment['order_id']); ?></a></td>
                                    <td><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                                    <td><?php echo (new DateTime($payment['payment_date']))->format('d.m.Y H:i'); ?></td>
                                    <td><?php echo formatEuroCurrency($payment['amount']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $payment['payment_method']))); ?></td>
                                    <td><?php echo htmlspecialchars($payment['transaction_id'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($payment['status'])); ?></td>
                                    <td class="action-buttons">
                                        <a href="?section=payments&action=edit&id=<?php echo $payment['payment_id']; ?>" class="edit-btn">Bearbeiten</a>
                                        <form method="POST" action="?section=payments&action=delete&id=<?php echo $payment['payment_id']; ?>" onsubmit="return confirm('Sicher? Diese Zahlung löschen?');" style="display:inline;">
                                            <button type="submit" name="delete_payment" class="delete-btn">Löschen</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php
                }
                break;

            case 'products':
                $productId = (int)($_GET['id'] ?? 0);
                $action = $_GET['action'] ?? '';

                if ($action === 'add' || ($action === 'edit' && $productId > 0)) {
                    $productToEdit = null;
                    if ($action === 'edit') {
                        $stmtEdit = $pdo->prepare("SELECT * FROM products WHERE product_id = :id");
                        $stmtEdit->execute([':id' => $productId]);
                        $productToEdit = $stmtEdit->fetch();
                        if (!$productToEdit) {
                             echo '<p class="alert error">Produkt nicht gefunden.</p>';
                             break;
                        }
                    }
                    $categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
                    ?>
                    <div class="admin-form-container">
                        <h3><?php echo ($action === 'add' ? 'Neues Produkt hinzufügen' : 'Produkt #' . htmlspecialchars($productId) . ' bearbeiten'); ?></h3>
                        <form method="POST" action="?section=products&action=<?php echo $action; ?><?php echo ($action === 'edit' ? '&id=' . $productId : ''); ?>">
                            <div class="form-group">
                                <label for="name">Name:</label>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($productToEdit['name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="description">Beschreibung:</label>
                                <textarea id="description" name="description"><?php echo htmlspecialchars($productToEdit['description'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="price">Preis (€):</label>
                                <input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo htmlspecialchars($productToEdit['price'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="stock_quantity">Lagerbestand:</label>
                                <input type="number" id="stock_quantity" name="stock_quantity" min="0" value="<?php echo htmlspecialchars($productToEdit['stock_quantity'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="image_url">Bild-URL:</label>
                                <input type="text" id="image_url" name="image_url" value="<?php echo htmlspecialchars($productToEdit['image_url'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="category_id">Kategorie:</label>
                                <select id="category_id" name="category_id" required>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category['category_id']); ?>" <?php echo (isset($productToEdit['category_id']) && $productToEdit['category_id'] == $category['category_id'] ? 'selected' : ''); ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <input type="checkbox" id="is_active" name="is_active" <?php echo (isset($productToEdit['is_active']) && $productToEdit['is_active'] ? 'checked' : ($action === 'add' ? 'checked' : '')); ?>>
                                <label for="is_active" style="display: inline;">Aktiv</label>
                            </div>
                            <button type="submit" name="<?php echo ($action === 'add' ? 'add_product' : 'update_product'); ?>"><?php echo ($action === 'add' ? 'Produkt hinzufügen' : 'Produkt aktualisieren'); ?></button>
                        </form>
                    </div>
                    <?php
                } else {
                    // GEÄNDERT: Sortierung nach p.product_id ASC
                    $stmt = $pdo->query("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.category_id ORDER BY p.product_id ASC");
                    $products = $stmt->fetchAll();
                    ?>
                    <div class="action-buttons">
                        <a href="?section=products&action=add" class="add-btn">Neues Produkt hinzufügen</a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Beschreibung</th>
                                <th>Preis</th>
                                <th>Lager</th>
                                <th>Kategorie</th>
                                <th>Aktiv</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['product_id']); ?></td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($product['description'], 0, 50)); ?><?php echo (strlen($product['description']) > 50 ? '...' : ''); ?></td>
                                    <td><?php echo formatEuroCurrency($product['price']); ?></td>
                                    <td><?php echo htmlspecialchars($product['stock_quantity']); ?></td>
                                    <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                    <td><?php echo ($product['is_active'] ? 'Ja' : 'Nein'); ?></td>
                                    <td class="action-buttons">
                                        <a href="?section=products&action=edit&id=<?php echo $product['product_id']; ?>" class="edit-btn">Bearbeiten</a>
                                        <form method="POST" action="?section=products&action=delete&id=<?php echo $product['product_id']; ?>" onsubmit="return confirm('Sicher? Dies löscht auch alle Bestellartikel, die dieses Produkt verwenden!');" style="display:inline;">
                                            <button type="submit" name="delete_product" class="delete-btn">Löschen</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php
                }
                break;

            case 'categories':
                $categoryId = (int)($_GET['id'] ?? 0);
                $action = $_GET['action'] ?? '';

                if ($action === 'add' || ($action === 'edit' && $categoryId > 0)) {
                    $categoryToEdit = null;
                    if ($action === 'edit') {
                        $stmtEdit = $pdo->prepare("SELECT * FROM categories WHERE category_id = :id");
                        $stmtEdit->execute([':id' => $categoryId]);
                        $categoryToEdit = $stmtEdit->fetch();
                        if (!$categoryToEdit) {
                            echo '<p class="alert error">Kategorie nicht gefunden.</p>';
                            break;
                        }
                    }
                    ?>
                    <div class="admin-form-container">
                        <h3><?php echo ($action === 'add' ? 'Neue Kategorie hinzufügen' : 'Kategorie #' . htmlspecialchars($categoryId) . ' bearbeiten'); ?></h3>
                        <form method="POST" action="?section=categories&action=<?php echo $action; ?><?php echo ($action === 'edit' ? '&id=' . $categoryId : ''); ?>">
                            <div class="form-group">
                                <label for="name">Name:</label>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($categoryToEdit['name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="description">Beschreibung:</label>
                                <textarea id="description" name="description"><?php echo htmlspecialchars($categoryToEdit['description'] ?? ''); ?></textarea>
                            </div>
                            <button type="submit" name="<?php echo ($action === 'add' ? 'add_category' : 'update_category'); ?>"><?php echo ($action === 'add' ? 'Kategorie hinzufügen' : 'Kategorie aktualisieren'); ?></button>
                        </form>
                    </div>
                    <?php
                } else {
                    $stmt = $pdo->query("SELECT * FROM categories ORDER BY category_id ASC");
                    $categories = $stmt->fetchAll();
                    ?>
                    <div class="action-buttons">
                        <a href="?section=categories&action=add" class="add-btn">Neue Kategorie hinzufügen</a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Beschreibung</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category['category_id']); ?></td>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($category['description'], 0, 100)); ?><?php echo (strlen($category['description']) > 100 ? '...' : ''); ?></td>
                                    <td class="action-buttons">
                                        <a href="?section=categories&action=edit&id=<?php echo $category['category_id']; ?>" class="edit-btn">Bearbeiten</a>
                                        <form method="POST" action="?section=categories&action=delete&id=<?php echo $category['category_id']; ?>" onsubmit="return confirm('Sicher? Dies löscht die Kategorie nur, wenn keine Produkte mehr zugeordnet sind!');" style="display:inline;">
                                            <button type="submit" name="delete_category" class="delete-btn">Löschen</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php
                }
                break;

            case 'pickup_dates':
                $dateId = (int)($_GET['id'] ?? 0);
                $action = $_GET['action'] ?? '';

                if ($action === 'add' || ($action === 'edit' && $dateId > 0)) {
                    $dateToEdit = null;
                    if ($action === 'edit') {
                        $stmtEdit = $pdo->prepare("SELECT * FROM pickup_dates WHERE pickup_date_id = :id");
                        $stmtEdit->execute([':id' => $dateId]);
                        $dateToEdit = $stmtEdit->fetch();
                        if (!$dateToEdit) {
                            echo '<p class="alert error">Abholtermin nicht gefunden.</p>';
                            break;
                        }
                    }
                    ?>
                    <div class="admin-form-container">
                        <h3><?php echo ($action === 'add' ? 'Neuen Abholtermin hinzufügen' : 'Abholtermin #' . htmlspecialchars($dateId) . ' bearbeiten'); ?></h3>
                        <form method="POST" action="?section=pickup_dates&action=<?php echo $action; ?><?php echo ($action === 'edit' ? '&id=' . $dateId : ''); ?>">
                            <div class="form-group">
                                <label for="pickup_datetime">Datum und Uhrzeit:</label>
                                <input type="datetime-local" id="pickup_datetime" name="pickup_datetime" value="<?php echo (new DateTime($dateToEdit['pickup_datetime'] ?? 'now'))->format('Y-m-d\TH:i'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="location">Ort:</label>
                                <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($dateToEdit['location'] ?? 'Standard Abholort'); ?>" required>
                            </div>
                            <div class="form-group">
                                <input type="checkbox" id="is_active" name="is_active" <?php echo (isset($dateToEdit['is_active']) && $dateToEdit['is_active'] ? 'checked' : ($action === 'add' ? 'checked' : '')); ?>>
                                <label for="is_active" style="display: inline;">Aktiv</label>
                            </div>
                             <div class="form-group">
                                <label for="notes">Notizen:</label>
                                <textarea id="notes" name="notes"><?php echo htmlspecialchars($dateToEdit['notes'] ?? ''); ?></textarea>
                            </div>
                            <button type="submit" name="<?php echo ($action === 'add' ? 'add_pickup_date' : 'update_pickup_date'); ?>"><?php echo ($action === 'add' ? 'Abholtermin hinzufügen' : 'Abholtermin aktualisieren'); ?></button>
                        </form>
                    </div>
                    <?php
                } else {
                    $stmt = $pdo->query("SELECT * FROM pickup_dates ORDER BY pickup_datetime DESC");
                    $pickupDates = $stmt->fetchAll();
                    ?>
                    <div class="action-buttons">
                        <a href="?section=pickup_dates&action=add" class="add-btn">Neuen Abholtermin hinzufügen</a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Datum/Uhrzeit</th>
                                <th>Ort</th>
                                <th>Aktiv</th>
                                <th>Notizen</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pickupDates as $date): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($date['pickup_date_id']); ?></td>
                                    <td><?php echo (new DateTime($date['pickup_datetime']))->format('d.m.Y H:i'); ?></td>
                                    <td><?php echo htmlspecialchars($date['location']); ?></td>
                                    <td><?php echo ($date['is_active'] ? 'Ja' : 'Nein'); ?></td>
                                    <td><?php echo htmlspecialchars(substr($date['notes'], 0, 50)); ?><?php echo (strlen($date['notes']) > 50 ? '...' : ''); ?></td>
                                    <td class="action-buttons">
                                        <a href="?section=pickup_dates&action=edit&id=<?php echo $date['pickup_date_id']; ?>" class="edit-btn">Bearbeiten</a>
                                        <form method="POST" action="?section=pickup_dates&action=delete&id=<?php echo $date['pickup_date_id']; ?>" onsubmit="return confirm('Sicher? Dies löscht den Abholtermin!');" style="display:inline;">
                                            <button type="submit" name="delete_pickup_date" class="delete-btn">Löschen</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php
                }
                break;

            case 'email_logs':
                // Alle E-Mail-Protokolle anzeigen
                $stmt = $pdo->query("SELECT * FROM email_logs ORDER BY sent_at DESC");
                $emailLogs = $stmt->fetchAll();
                ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>An E-Mail</th>
                            <th>Betreff</th>
                            <th>Status</th>
                            <th>Versendet am</th>
                            <th>Fehlermeldung</th>
                            <th>Bestell-ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emailLogs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['log_id']); ?></td>
                                <td><?php echo htmlspecialchars($log['to_email']); ?></td>
                                <td><?php echo htmlspecialchars($log['subject']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($log['status'])); ?></td>
                                <td><?php echo (new DateTime($log['sent_at']))->format('d.m.Y H:i'); ?></td>
                                <td><?php echo htmlspecialchars($log['error_message'] ?: 'N/A'); ?></td>
                                <td><?php echo ($log['order_id'] ? '<a href="?section=orders&action=edit&id=' . htmlspecialchars($log['order_id']) . '">' . htmlspecialchars($log['order_id']) . '</a>' : 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php
                break;

            default:
                echo '<p>Willkommen im Admin Panel. Wählen Sie eine Option aus dem Menü.</p>';
                break;
        }
        ?>
    </div>
</div>
<?php
// Footer wird vom index.php geladen, daher hier nicht erneut ausgeben.
?>