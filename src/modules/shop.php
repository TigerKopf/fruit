<?php
// modules/shop.php

// Stellt sicher, dass die Session gestartet ist.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Initialisiert den Warenkorb in der Session, falls nicht vorhanden.
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Stellt die Datenbankverbindung sicher.
global $pdo;
if (!isset($pdo)) {
    // Fallback, falls die globale Variable nicht gesetzt ist.
    require_once ROOT_PATH . 'include/db.php';
    $pdo = getDbConnection();
}

// Initialisierung von Variablen
$products_by_category = [];
$error_message = '';

try {
    // Ruft alle aktiven Produkte ab und sortiert sie nach Kategorie und Produkt-ID.
    $stmt = $pdo->query(
        "SELECT p.*, c.name as category_name 
         FROM products p 
         JOIN categories c ON p.category_id = c.category_id 
         WHERE p.is_active = TRUE 
         ORDER BY c.category_id ASC, p.product_id ASC"
    );
    $all_products = $stmt->fetchAll();

    // Gruppiert Produkte nach ihrem Kategorienamen.
    foreach ($all_products as $product) {
        $products_by_category[$product['category_name']][] = $product;
    }

} catch (PDOException $e) {
    error_log("Datenbankfehler auf der Shop-Seite: " . $e->getMessage());
    $error_message = "Ein unerwarteter Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.";
}

// Berechnet den initialen Gesamtbetrag und die Artikelanzahl des Warenkorbs.
$cart_total = 0;
$cart_item_count = 0;
foreach ($_SESSION['cart'] as $item) {
    $cart_total += $item['price'] * $item['quantity'];
    $cart_item_count += $item['quantity'];
}
?>

<!-- Wrapper für Hauptinhalt und Seitenleiste -->
<div class="site-content-wrapper">

    <main>
        <?php if ($error_message): ?>
            <div class="alert error"><?php echo htmlspecialchars($error_message); ?></div>
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
                                // Logik für ausverkaufte Produkte
                                $is_sold_out = ((int)$product['stock_quantity'] <= 0);
                                $button_text = $is_sold_out ? 'Ausverkauft' : 'In den Warenkorb';
                                $is_disabled = $is_sold_out ? 'disabled' : '';
                                $input_value = $is_sold_out ? '0' : '1';
                            ?>
                                <div class="product-item">
                                    <div class="product-image-container">
                                        <img src="<?php echo htmlspecialchars($product['image_url'] ?: '/_placeholder.png'); ?>" 
                                             alt="Produktbild von <?php echo htmlspecialchars($product['name']); ?>" 
                                             loading="lazy" decoding="async">
                                        <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                    </div>
                                    <p class="product-quantity-price-line">
                                        <span class="product-unit"><?php echo htmlspecialchars($product['description']); ?> für</span> 
                                        <span class="product-price-value"><?php echo formatEuroCurrency($product['price']); ?></span>
                                    </p>
                                    <div class="product-controls">
                                        <input type="number" class="quantity-input" value="<?php echo $input_value; ?>" min="0" 
                                               max="<?php echo (int)$product['stock_quantity']; ?>" 
                                               data-product-id="<?php echo (int)$product['product_id']; ?>" <?php echo $is_disabled; ?>>
                                        <button class="add-to-cart-btn"
                                                data-product-id="<?php echo (int)$product['product_id']; ?>"
                                                data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                                data-product-price="<?php echo htmlspecialchars($product['price']); ?>"
                                                data-product-stock="<?php echo (int)$product['stock_quantity']; ?>"
                                                data-product-image="<?php echo htmlspecialchars($product['image_url'] ?: '/_placeholder.png'); ?>"
                                                data-product-description="<?php echo htmlspecialchars($product['description']); ?>"
                                                <?php echo $is_disabled; ?>>
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
        <!-- Warenkorb-Header für Desktop -->
        <div class="cart-header-desktop">
            <h2>Ihr Warenkorb</h2>
        </div>

        <!-- Warenkorb-Header für Mobilgeräte (klappbar) -->
        <div class="cart-header-mobile">
            <span class="cart-mobile-summary">
                Warenkorb (<span id="mobile-cart-item-count"><?php echo $cart_item_count; ?></span>) - <span id="mobile-cart-total-summary"><?php echo formatEuroCurrency($cart_total); ?></span>
            </span>
            <button id="cart-toggle-mobile" class="cart-toggle-button" aria-label="Warenkorb ein-/ausklappen">
                <span class="toggle-text">Details</span> <span class="toggle-icon">▼</span>
            </button>
        </div>

        <!-- Hauptinhalt des Warenkorbs -->
        <div class="cart-body-content">
            <div class="cart-items-scrollable-wrapper">
                <ul id="cart-items">
                    <?php if (empty($_SESSION['cart'])): ?>
                        <li id="cart-empty-message">Ihr Warenkorb ist leer.</li>
                    <?php else: ?>
                        <?php foreach ($_SESSION['cart'] as $productId => $item): ?>
                            <li class="cart-item" data-product-id="<?php echo htmlspecialchars($productId); ?>">
                                <img src="<?php echo htmlspecialchars($item['image_url'] ?: '/_placeholder.png'); ?>" 
                                     alt="Bild von <?php echo htmlspecialchars($item['name']); ?>" class="cart-item-image" 
                                     loading="lazy" decoding="async">
                                <div class="cart-item-info">
                                    <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                    <p><?php echo formatEuroCurrency((float)$item['price'] * (int)$item['quantity']); ?></p>
                                </div>
                                <div class="cart-item-controls">
                                    <input type="number" class="cart-quantity-input" value="<?php echo (int)$item['quantity']; ?>" min="1" 
                                           max="<?php echo (int)$item['stock']; ?>" data-product-id="<?php echo htmlspecialchars($productId); ?>">
                                    <button class="remove-item-btn" data-product-id="<?php echo htmlspecialchars($productId); ?>" aria-label="Artikel entfernen">×</button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
            <div id="cart-total-container">
                <span>Gesamt:</span> <span id="cart-total"><?php echo formatEuroCurrency($cart_total); ?></span>
            </div>
            <button class="checkout-button" id="open-checkout-modal" <?php echo empty($_SESSION['cart']) ? 'disabled' : ''; ?>>
                Weiter
            </button>
        </div>
    </aside>

</div> <!-- Ende .site-content-wrapper -->