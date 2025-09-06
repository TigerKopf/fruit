<?php
// modules/admin/customers.php
// Wird von modules/admin/index.php geladen.
// $pdo, formatEuroCurrency, $currentSection, $id, $action, $actionStatus, $actionMessage sind bereits verfügbar.

// POST-Aktionen für Kunden verarbeiten (Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'edit' && $id > 0 && isset($_POST['update_customer'])) {
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
    } catch (Exception $e) {
        error_log("Admin-Kundenaktion Fehler: " . $e->getMessage());
        header('Location: ?section=customers&status=error&msg=' . urlencode($e->getMessage()));
        exit();
    }
}

// HTML-Ausgabe für Kunden
if ($action === 'edit' && $id > 0) {
    $stmtEdit = $pdo->prepare("SELECT * FROM users WHERE user_id = :id");
    $stmtEdit->execute([':id' => $id]);
    $customerToEdit = $stmtEdit->fetch();

    if (!$customerToEdit) {
        echo '<p class="alert error">Kunde nicht gefunden.</p>';
        return;
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
                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($customerToEdit['last_name']); ?>" required>
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