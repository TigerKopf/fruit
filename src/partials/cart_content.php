<?php
// partials/cart_content.php
// Dieses Skript wird von index.php eingebunden, um den initialen Warenkorb anzuzeigen.
// Es geht davon aus, dass session_start() und ROOT_PATH bereits im aufrufenden Skript definiert wurden.

// Sicherstellen, dass der Warenkorb in der Session initialisiert ist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$cart = $_SESSION['cart'];
$totalAmount = 0;
?>

<div>
    <?php if (count($cart) > 0): ?>
        <ul id="cart-items">
            <?php foreach ($cart as $productId => $item):
                $itemTotal = $item['price'] * $item['quantity'];
                $totalAmount += $itemTotal;
            ?>
                <li class="cart-item">
                    <!-- GEÄNDERT: loading="lazy" und decoding="async" hinzugefügt -->
                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>: <?= htmlspecialchars($item['description']) ?>" class="cart-item-img" loading="lazy" decoding="async">
                    <div class="cart-item-details">
                        <h4><?= htmlspecialchars($item['name']) ?></h4>
                        <p><?= number_format($item['price'], 2, ',', '.') ?> € x</p>
                    </div>
                    <div class="cart-item-actions">
                        <input type="number" min="1" value="<?= htmlspecialchars($item['quantity']) ?>" data-product-id="<?= htmlspecialchars($productId) ?>" class="quantity-input">
                        <button class="remove-item-btn" data-product-id="<?= htmlspecialchars($productId) ?>">X</button>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <p id="cart-total">Gesamt: <?= number_format($totalAmount, 2, ',', '.') ?> €</p>
    <?php else: ?>
        <p>Ihr Warenkorb ist leer.</p>
        <p id="cart-total">Gesamt: 0,00 €</p>
    <?php endif; ?>
</div>