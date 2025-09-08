<?php
// modules/admin/orders.php
// Wird von modules/admin/index.php geladen.
// $pdo, formatEuroCurrency, $currentSection, $id, $action, $actionStatus, $actionMessage sind bereits verfügbar.

// POST-Aktionen für Bestellungen verarbeiten (Status-Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'edit' && $id > 0 && isset($_POST['update_order'])) {
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
                header('Location: ?section=orders&status=success&msg=' . urlencode('Bestellstatus erfolgreich aktualisiert. Eine E-Mail wurde an den Kunden gesendet.'));
            } else {
                header('Location: ?section=orders&status=success&msg=' . urlencode('Bestellstatus war bereits aktuell. Keine Änderung vorgenommen.'));
            }
            exit();
        }
    } catch (Exception $e) {
        error_log("Admin-Bestellaktion Fehler: " . $e->getMessage());
        header('Location: ?section=orders&status=error&msg=' . urlencode($e->getMessage()));
        exit();
    }
}

// HTML-Ausgabe für Bestellungen
if ($action === 'edit' && $id > 0) {
    $stmtEdit = $pdo->prepare("SELECT o.*, u.first_name, u.last_name, u.email, u.phone_number, pd.pickup_datetime, pd.location FROM orders o JOIN users u ON o.user_id = u.user_id LEFT JOIN pickup_dates pd ON o.pickup_date_id = pd.pickup_date_id WHERE o.order_id = :id");
    $stmtEdit->execute([':id' => $id]);
    $orderToEdit = $stmtEdit->fetch();

    if (!$orderToEdit) {
         echo '<p class="alert error">Bestellung nicht gefunden.</p>';
         return;
    }

    // Bestelldetails abrufen
    $stmtItems = $pdo->prepare("SELECT oi.*, p.name as product_name FROM order_items oi JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = :order_id");
    $stmtItems->execute([':order_id' => $id]);
    $orderItems = $stmtItems->fetchAll();

    // Zahlungen für diese Bestellung abrufen
    $stmtPayments = $pdo->prepare("SELECT * FROM payments WHERE order_id = :order_id ORDER BY payment_date ASC");
    $stmtPayments->execute([':order_id' => $id]);
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
                    <td class="action-buttons">
                        <a href="?section=orders&action=edit&id=<?php echo $order['order_id']; ?>" class="edit-btn">Details/Bearbeiten</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}