<?php
// modules/admin/pickup_dates.php
// Wird von modules/admin/index.php geladen.
// $pdo, formatEuroCurrency, $currentSection, $id, $action, $actionStatus, $actionMessage sind bereits verfügbar.

// POST-Aktionen für Abholtermine verarbeiten (Hinzufügen, Bearbeiten, Löschen)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'add' && isset($_POST['add_pickup_date'])) {
            $stmt = $pdo->prepare("INSERT INTO pickup_dates (pickup_datetime, location, is_active, notes) VALUES (:pickup_datetime, :location, :is_active, :notes)");
            $stmt->execute([
                ':pickup_datetime' => $_POST['pickup_datetime'],
                ':location' => $_POST['location'],
                ':is_active' => isset($_POST['is_active']) ? 1 : 0,
                ':notes' => $_POST['notes']
            ]);
            header('Location: ?section=pickup_dates&status=success');
            exit();
        } elseif ($action === 'edit' && $id > 0 && isset($_POST['update_pickup_date'])) {
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
        } elseif ($action === 'delete' && $id > 0 && isset($_POST['delete_pickup_date'])) {
             $stmt = $pdo->prepare("DELETE FROM pickup_dates WHERE pickup_date_id = :id");
             $stmt->execute([':id' => $id]);
             header('Location: ?section=pickup_dates&status=success');
             exit();
        }
    } catch (Exception $e) {
        error_log("Admin-Abholterminaktion Fehler: " . $e->getMessage());
        header('Location: ?section=pickup_dates&status=error&msg=' . urlencode($e->getMessage()));
        exit();
    }
}

// HTML-Ausgabe für Abholtermine
if ($action === 'add' || ($action === 'edit' && $id > 0)) {
    $dateToEdit = null;
    if ($action === 'edit') {
        $stmtEdit = $pdo->prepare("SELECT * FROM pickup_dates WHERE pickup_date_id = :id");
        $stmtEdit->execute([':id' => $id]);
        $dateToEdit = $stmtEdit->fetch();
        if (!$dateToEdit) {
            echo '<p class="alert error">Abholtermin nicht gefunden.</p>';
            return;
        }
    }
    ?>
    <div class="admin-form-container">
        <h3><?php echo ($action === 'add' ? 'Neuen Abholtermin hinzufügen' : 'Abholtermin #' . htmlspecialchars($id) . ' bearbeiten'); ?></h3>
        <form method="POST" action="?section=pickup_dates&action=<?php echo $action; ?><?php echo ($action === 'edit' ? '&id=' . $id : ''); ?>">
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