<?php
// modules/admin/categories.php
// Wird von modules/admin/index.php geladen.
// $pdo, formatEuroCurrency, $currentSection, $id, $action, $actionStatus, $actionMessage sind bereits verfügbar.

// POST-Aktionen für Kategorien verarbeiten (Hinzufügen, Bearbeiten, Löschen)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'add' && isset($_POST['add_category'])) {
            $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (:name, :description)");
            $stmt->execute([
                ':name' => $_POST['name'],
                ':description' => $_POST['description']
            ]);
            header('Location: ?section=categories&status=success');
            exit();
        } elseif ($action === 'edit' && $id > 0 && isset($_POST['update_category'])) {
            $stmt = $pdo->prepare("UPDATE categories SET name = :name, description = :description, updated_at = CURRENT_TIMESTAMP WHERE category_id = :id");
            $stmt->execute([
                ':name' => $_POST['name'],
                ':description' => $_POST['description'],
                ':id' => $id
            ]);
            header('Location: ?section=categories&status=success');
            exit();
        } elseif ($action === 'delete' && $id > 0 && isset($_POST['delete_category'])) {
            // Überprüfen, ob Produkte dieser Kategorie zugeordnet sind, bevor gelöscht wird
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
    } catch (Exception $e) { // Fange auch die selbstgeworfene Exception
        error_log("Admin-Kategorienaktion Fehler: " . $e->getMessage());
        header('Location: ?section=categories&status=error&msg=' . urlencode($e->getMessage()));
        exit();
    } catch (PDOException $e) {
         error_log("Admin-Kategorienaktion Fehler: " . $e->getMessage());
         header('Location: ?section=categories&status=error&msg=' . urlencode('Datenbankfehler: ' . $e->getMessage()));
         exit();
    }
}

// HTML-Ausgabe für Kategorien
if ($action === 'add' || ($action === 'edit' && $id > 0)) {
    $categoryToEdit = null;
    if ($action === 'edit') {
        $stmtEdit = $pdo->prepare("SELECT * FROM categories WHERE category_id = :id");
        $stmtEdit->execute([':id' => $id]);
        $categoryToEdit = $stmtEdit->fetch();
        if (!$categoryToEdit) {
            echo '<p class="alert error">Kategorie nicht gefunden.</p>';
            return;
        }
    }
    ?>
    <div class="admin-form-container">
        <h3><?php echo ($action === 'add' ? 'Neue Kategorie hinzufügen' : 'Kategorie #' . htmlspecialchars($id) . ' bearbeiten'); ?></h3>
        <form method="POST" action="?section=categories&action=<?php echo $action; ?><?php echo ($action === 'edit' ? '&id=' . $id : ''); ?>">
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
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
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