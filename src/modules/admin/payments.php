<?php
// modules/admin/payments.php
// Wird von modules/admin/index.php geladen.
// $pdo, formatEuroCurrency, $currentSection, $id, $action, $actionStatus, $actionMessage sind bereits verfügbar.

// POST-Aktionen für Zahlungen verarbeiten (Hinzufügen, Bearbeiten, Löschen)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'add' && isset($_POST['add_payment'])) {
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
        } elseif ($action === 'edit' && $id > 0 && isset($_POST['update_payment'])) {
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
        } elseif ($action === 'delete' && $id > 0 && isset($_POST['delete_payment'])) {
             $stmt = $pdo->prepare("DELETE FROM payments WHERE payment_id = :id");
             $stmt->execute([':id' => $id]);
             // Optional: E-Mail senden, dass Zahlung storniert/gelöscht wurde.
             header('Location: ?section=payments&status=success');
             exit();
        }
    } catch (Exception $e) {
        error_log("Admin-Zahlungsaktion Fehler: " . $e->getMessage());
        header('Location: ?section=' . $currentSection . '&status=error&msg=' . urlencode($e->getMessage()));
        exit();
    }
}

// HTML-Ausgabe für Zahlungen
$orderIdFromGet = (int)($_GET['order_id'] ?? 0); // Optional, wenn von Bestellseite verlinkt

if ($action === 'add' || ($action === 'edit' && $id > 0)) {
    $paymentToEdit = null;
    if ($action === 'edit') {
        $stmtEdit = $pdo->prepare("SELECT * FROM payments WHERE payment_id = :id");
        $stmtEdit->execute([':id' => $id]);
        $paymentToEdit = $stmtEdit->fetch();
        if (!$paymentToEdit) {
            echo '<p class="alert error">Zahlung nicht gefunden.</p>';
            return;
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
        <h3><?php echo ($action === 'add' ? 'Neue Zahlung hinzufügen' : 'Zahlung #' . htmlspecialchars($id) . ' bearbeiten'); ?></h3>
        <?php if ($orderInfo): ?>
            <p><strong>Bestellung:</strong> #<?php echo htmlspecialchars($orderInfo['order_id']); ?> (Kunde: <?php echo htmlspecialchars($orderInfo['first_name'] . ' ' . $orderInfo['last_name']); ?>, Gesamt: <?php echo formatEuroCurrency($orderInfo['total_amount']); ?>)</p>
        <?php elseif ($action === 'add'): ?>
            <div class="form-group">
                <label for="order_id">Bestell-ID:</label>
                <input type="number" id="order_id" name="order_id" value="<?php echo htmlspecialchars($orderIdFromGet); ?>" required min="1">
            </div>
        <?php endif; ?>

        <form method="POST" action="?section=payments&action=<?php echo $action; ?><?php echo ($action === 'edit' ? '&id=' . $id : ''); ?>">
            <?php if ($orderInfo): // Wenn von Bestellung aus hinzugefügt wird, die Order-ID übergeben ?>
                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($orderInfo['order_id']); ?>">
                <input type="hidden" name="return_to_order_id" value="<?php echo htmlspecialchars($orderInfo['order_id']); ?>">
            <?php elseif ($action === 'add' && $orderIdFromGet): // Wenn direkt auf Payments-Seite hinzugefügt wird und order_id in GET ?>
                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($orderIdFromGet); ?>">
                <input type="hidden" name="return_to_order_id" value="<?php echo htmlspecialchars($orderIdFromGet); ?>">
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