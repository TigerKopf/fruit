<?php
// modules/shop.php

// session_start() sollte in loader.php oder index.php aufgerufen werden.
// Wenn nicht, hier aufrufen:
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Warenkorb in der Session initialisieren, falls noch nicht vorhanden
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Datenbankverbindung abrufen (wird über loader.php und getDbConnection() verfügbar sein)
global $pdo; // Annahme, dass $pdo bereits global über include/db.php gesetzt ist
if (!isset($pdo)) {
    // Fallback falls $pdo nicht global ist, aber getDbConnection() existiert
    require_once ROOT_PATH . 'include/db.php';
    $pdo = getDbConnection();
}

// HINWEIS: Die Funktion formatEuroCurrency() wird jetzt über include/helpers.php geladen und ist global verfügbar.

$products_by_category = [];
$error_message = '';
$success_message = '';

try {
    // Produkte und Kategorien abrufen
    // Sicherstellen, dass image_url und description auch abgerufen werden
    // GEÄNDERT: Sortierung nach c.category_id ASC und p.product_id ASC
    $stmt = $pdo->query("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.category_id WHERE p.is_active = TRUE ORDER BY c.category_id ASC, p.product_id ASC");
    $all_products = $stmt->fetchAll();

    foreach ($all_products as $product) {
        $products_by_category[$product['category_name']][] = $product;
    }

} catch (PDOException $e) {
    error_log("Datenbankfehler auf Shop-Seite: " . $e->getMessage());
    $error_message = "Es ist ein Fehler aufgetreten. Bitte versuchen Sie es später erneut.";
}

// Gesamtbetrag des Warenkorbs berechnen
$cart_total = 0;
$cart_item_count = 0;
foreach ($_SESSION['cart'] as $item_id => $item) {
    $cart_total += $item['price'] * $item['quantity'];
    $cart_item_count += $item['quantity'];
}
?>

<!-- Begin site-content-wrapper, which now wraps both the main product content and the cart sidebar -->
<div class="site-content-wrapper">

    <main>

        <?php if ($error_message): ?>
            <div class="alert error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="alert success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <div class="product-list">
            <?php if (empty($products_by_category)): ?>
                <p>Derzeit sind keine Produkte verfügbar.</p>
            <?php else: ?>
                <?php foreach ($products_by_category as $category_name => $products): ?>
                    <section class="category-section">
                        <h2><?php echo htmlspecialchars($category_name); ?></h2>
                        <div class="product-grid">
                            <?php foreach ($products as $product):
                                // Prüfen, ob das Produkt ausverkauft ist
                                $is_sold_out = ((int)$product['stock_quantity'] <= 0);
                                $button_text = $is_sold_out ? 'Ausverkauft' : 'In den Warenkorb';
                                $disabled_attr = $is_sold_out ? 'disabled' : '';
                                // ACHTUNG: '0' ist hier korrekt, nicht 0, da es als String-Attribut übergeben wird
                                $input_value = $is_sold_out ? '0' : '1'; 
                                $input_disabled_attr = $is_sold_out ? 'disabled' : '';
                            ?>
                                <div class="product-item">
                                    <img src="<?php echo htmlspecialchars($product['image_url'] ?: '/_placeholder.png'); ?>" alt="Bild von <?php echo htmlspecialchars($product['name']); ?> (<?php echo htmlspecialchars($product['description']); ?>)" loading="lazy" decoding="async">
                                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                    <p class="product-quantity-price-line">
                                        <span class="product-unit"><?php echo htmlspecialchars($product['description']); ?> für</span> <span class="product-price-value"><?php echo formatEuroCurrency($product['price']); ?></span>
                                    </p>
                                    <div class="product-controls">
                                        <input type="number" class="quantity-input" value="<?php echo $input_value; ?>" min="0" max="<?php echo (int)$product['stock_quantity']; ?>" data-product-id="<?php echo (int)$product['product_id']; ?>" <?php echo $input_disabled_attr; ?>>
                                        <button class="add-to-cart-btn" data-product-id="<?php echo (int)$product['product_id']; ?>"
                                                data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                                data-product-price="<?php echo htmlspecialchars($product['price']); ?>"
                                                data-product-stock="<?php echo (int)$product['stock_quantity']; ?>"
                                                data-product-image="<?php echo htmlspecialchars($product['image_url'] ?: '/_placeholder.png'); ?>"
                                                data-product-description="<?php echo htmlspecialchars($product['description']); ?>"
                                                <?php echo $disabled_attr; ?>>
                                            <?php echo $button_text; ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <aside class="cart-sidebar">
        <!-- Desktop Warenkorb Header -->
        <div class="cart-header-desktop">
            <h2>Ihr Warenkorb</h2>
        </div>

        <!-- Mobiler Warenkorb Header (zusammenklappbar) -->
        <div class="cart-header-mobile">
            <span class="cart-mobile-summary">
                Warenkorb (<span id="mobile-cart-item-count"><?php echo $cart_item_count; ?></span> Artikel) - <span id="mobile-cart-total-summary"><?php echo formatEuroCurrency($cart_total); ?></span>
            </span>
            <button id="cart-toggle-mobile" class="cart-toggle-button">
                <span class="toggle-text">Details</span> <span class="toggle-icon">▼</span>
            </button>
        </div>

        <!-- Warenkorb Inhalt (klappt auf Mobile ein/aus) -->
        <div class="cart-body-content">
            <!-- NEU: Wrapper für scrollbare Items -->
            <div class="cart-items-scrollable-wrapper">
                <ul id="cart-items">
                    <?php if (empty($_SESSION['cart'])): ?>
                        <li id="cart-empty-message">Ihr Warenkorb ist leer.</li>
                    <?php else: ?>
                        <?php foreach ($_SESSION['cart'] as $productId => $item): ?>
                            <li class="cart-item" data-product-id="<?php echo htmlspecialchars($productId); ?>">
                                <img src="<?php echo htmlspecialchars($item['image_url'] ?: '/_placeholder.png'); ?>" alt="Bild von <?php echo htmlspecialchars($item['name']); ?>: <?php echo htmlspecialchars($item['description']); ?>" class="cart-item-image" loading="lazy" decoding="async">
                                <div class="cart-item-info">
                                    <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                    <p><?php echo formatEuroCurrency(floatval($item['price']) * intval($item['quantity'])); ?></p>
                                </div>
                                <div class="cart-item-controls">
                                    <input type="number" class="cart-quantity-input" value="<?php echo htmlspecialchars($item['quantity']); ?>" min="1" max="<?php echo htmlspecialchars($item['stock']); ?>" data-product-id="<?php echo htmlspecialchars($productId); ?>">
                                    <button class="remove-item-btn" data-product-id="<?php echo htmlspecialchars($productId); ?>">×</button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
            <div id="cart-total-container">
                <span>Gesamt:</span> <span id="cart-total"><?php echo formatEuroCurrency($cart_total); ?></span>
            </div>
            <button class="checkout-button" id="open-checkout-modal" <?php echo empty($_SESSION['cart']) ? 'disabled' : ''; ?>>Weiter</button>
        </div>
    </aside>

</div>