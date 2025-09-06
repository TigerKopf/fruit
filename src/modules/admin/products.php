<?php
// modules/admin/products.php
// Wird von modules/admin/index.php geladen.
// $pdo, formatEuroCurrency, $currentSection, $id, $action, $actionStatus, $actionMessage sind bereits verfügbar.

// POST-Aktionen für Produkte verarbeiten (Hinzufügen, Bearbeiten, Löschen)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'add' && isset($_POST['add_product'])) {
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
        } elseif ($action === 'edit' && $id > 0 && isset($_POST['update_product'])) {
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
        } elseif ($action === 'delete' && $id > 0 && isset($_POST['delete_product'])) {
             $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = :id");
             $stmt->execute([':id' => $id]);
             header('Location: ?section=products&status=success');
             exit();
        }
    } catch (Exception $e) {
        error_log("Admin-Produktaktion Fehler: " . $e->getMessage());
        header('Location: ?section=products&status=error&msg=' . urlencode($e->getMessage()));
        exit();
    }
}

// HTML-Ausgabe für Produkte
if ($action === 'add' || ($action === 'edit' && $id > 0)) {
    $productToEdit = null;
    if ($action === 'edit') {
        $stmtEdit = $pdo->prepare("SELECT * FROM products WHERE product_id = :id");
        $stmtEdit->execute([':id' => $id]);
        $productToEdit = $stmtEdit->fetch();
        if (!$productToEdit) {
             echo '<p class="alert error">Produkt nicht gefunden.</p>';
             return;
        }
    }
    $categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
    ?>
    <div class="admin-form-container">
        <h3><?php echo ($action === 'add' ? 'Neues Produkt hinzufügen' : 'Produkt #' . htmlspecialchars($id) . ' bearbeiten'); ?></h3>
        <form method="POST" action="?section=products&action=<?php echo $action; ?><?php echo ($action === 'edit' ? '&id=' . $id : ''); ?>">
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
    $stmt = $pdo->query("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.category_id ORDER BY p.name");
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