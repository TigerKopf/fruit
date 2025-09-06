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

/**
 * Formatiert einen Betrag in Euro mit spezifischen Regeln für Nachkommastellen.
 * Zeigt zwei Nachkommastellen nur an, wenn sie nicht .00 sind.
 *
 * @param float $amount Der zu formatierende Betrag.
 * @return string Der formatierte Betrag mit Euro-Symbol.
 */
function formatEuroCurrency(float $amount): string {
    // Überprüfen, ob der Betrag ganze Zahlen hat (keine Nachkommastellen oder .00)
    if (fmod($amount, 1.0) == 0) {
        return number_format($amount, 0, ',', '.') . ' €';
    } else {
        // Andernfalls mit zwei Nachkommastellen formatieren
        return number_format($amount, 2, ',', '.') . ' €';
    }
}

$products_by_category = [];
$pickup_dates = [];
$error_message = '';
$success_message = '';

try {
    // Produkte und Kategorien abrufen
    // Sicherstellen, dass image_url und description auch abgerufen werden
    $stmt = $pdo->query("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.category_id WHERE p.is_active = TRUE ORDER BY c.name, p.name");
    $all_products = $stmt->fetchAll();

    foreach ($all_products as $product) {
        $products_by_category[$product['category_name']][] = $product;
    }

    // Aktive Abholtermine abrufen
    $stmt_dates = $pdo->query("SELECT * FROM pickup_dates WHERE is_active = TRUE AND pickup_datetime >= NOW() ORDER BY pickup_datetime ASC");
    $pickup_dates = $stmt_dates->fetchAll();

} catch (PDOException $e) {
    error_log("Datenbankfehler auf Shop-Seite: " . $e->getMessage());
    $error_message = "Es ist ein Fehler aufgetreten. Bitte versuchen Sie es später erneut.";
}

// Gesamtbetrag des Warenkorbs berechnen
$cart_total = 0;
foreach ($_SESSION['cart'] as $item_id => $item) {
    $cart_total += $item['price'] * $item['quantity'];
}

// Der <style>-Block wurde entfernt, da alles in assets/styles.css ausgelagert wurde.
?>

<!-- Begin site-content-wrapper, which now wraps both the main product content and the cart sidebar -->
<div class="site-content-wrapper">

    <main>
        <h1>Unser Produktsortiment</h1>

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
                            <?php foreach ($products as $product): ?>
                                <div class="product-item">
                                    <img src="<?php echo htmlspecialchars($product['image_url'] ?: '/_placeholder.png'); ?>" alt="Bild von <?php echo htmlspecialchars($product['name']); ?>: <?php echo htmlspecialchars($product['description']); ?>">
                                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                    <p class="description"><?php echo htmlspecialchars($product['description']); ?></p>
                                    <div class="price"><?php echo formatEuroCurrency($product['price']); ?></div>
                                    <div class="product-controls">
                                        <input type="number" class="quantity-input" value="1" min="1" max="<?php echo (int)$product['stock_quantity']; ?>" data-product-id="<?php echo (int)$product['product_id']; ?>">
                                        <button class="add-to-cart-btn" data-product-id="<?php echo (int)$product['product_id']; ?>"
                                                data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                                data-product-price="<?php echo htmlspecialchars($product['price']); ?>"
                                                data-product-stock="<?php echo (int)$product['stock_quantity']; ?>"
                                                data-product-image="<?php echo htmlspecialchars($product['image_url'] ?: '/_placeholder.png'); ?>"
                                                data-product-description="<?php echo htmlspecialchars($product['description']); ?>"
                                                <?php echo ($product['stock_quantity'] <= 0) ? 'disabled' : ''; ?>>
                                            <?php echo ($product['stock_quantity'] <= 0) ? 'Ausverkauft' : 'In den Warenkorb'; ?>
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
        <h2>Ihr Warenkorb</h2>
        <div id="cart-items-scrollable"> <!-- Added wrapper for scrollable items -->
            <ul id="cart-items">
                <?php if (empty($_SESSION['cart'])): ?>
                    <li id="cart-empty-message">Ihr Warenkorb ist leer.</li>
                <?php else: ?>
                    <?php foreach ($_SESSION['cart'] as $productId => $item): ?>
                        <li class="cart-item" data-product-id="<?php echo htmlspecialchars($productId); ?>">
                            <img src="<?php echo htmlspecialchars($item['image_url'] ?: '/_placeholder.png'); ?>" alt="Bild von <?php echo htmlspecialchars($item['name']); ?>: <?php echo htmlspecialchars($item['description']); ?>" class="cart-item-image">
                            <div class="cart-item-info">
                                <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                <p><?php echo formatEuroCurrency($item['price'] * $item['quantity']); ?></p>
                            </div>
                            <div class="cart-item-controls">
                                <input type="number" class="cart-quantity-input" value="<?php echo (int)$item['quantity']; ?>" min="1" max="<?php echo (int)$item['stock']; ?>" data-product-id="<?php echo htmlspecialchars($productId); ?>">
                                <button class="remove-item-btn" data-product-id="<?php echo htmlspecialchars($productId); ?>">&times;</button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
        <div id="cart-total-container">
            <strong>Gesamt: <span id="cart-total"><?php echo formatEuroCurrency($cart_total); ?></span></strong>
        </div>
        <button class="checkout-button" id="open-checkout-modal" <?php echo empty($_SESSION['cart']) ? 'disabled' : ''; ?>>Weiter zur Kasse</button>
    </aside>

    <!-- Checkout Modal remains here -->
    <div id="checkoutModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h3>Bestellung abschliessen</h3>
            <div id="checkout-message" class="alert" style="display:none;"></div>
            <form id="checkoutForm">
                <div class="form-group">
                    <label for="firstName">Vorname:</label>
                    <input type="text" id="firstName" name="firstName" required>
                </div>
                <div class="form-group">
                    <label for="lastName">Nachname:</label>
                    <input type="text" id="lastName" name="lastName" required>
                </div>
                <div class="form-group">
                    <label for="email">E-Mail:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="phone">Telefon (optional):</label>
                    <input type="text" id="phone" name="phone">
                </div>

                <div class="form-group">
                    <label>Zahlungsmethode:</label>
                    <div class="radio-option">
                        <input type="radio" id="paymentBankTransfer" name="paymentMethod" value="bank_transfer" required checked>
                        <label for="paymentBankTransfer">Überweisung</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="paymentCash" name="paymentMethod" value="cash" required>
                        <label for="paymentCash">Barzahlung bei Abholung</label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="pickupDate">Abholtermin:</label>
                    <select id="pickupDate" name="pickupDate" required>
                        <option value="">Bitte wählen</option>
                        <?php foreach ($pickup_dates as $date): ?>
                            <option value="<?php echo htmlspecialchars($date['pickup_date_id']); ?>">
                                <?php echo (new DateTime($date['pickup_datetime']))->format('d.m.Y H:i') . ' Uhr'; ?> - <?php echo htmlspecialchars($date['location']); ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if (empty($pickup_dates)): ?>
                            <option value="" disabled>Keine Abholtermine verfügbar</option>
                        <?php endif; ?>
                    </select>
                </div>

                <button type="submit" id="checkout-submit-button">Bestellung abschicken</button>
            </form>
        </div>
    </div>

</div><!-- End site-content-wrapper -->

<script>
    // Helper function for consistent currency formatting in JavaScript
    const formatEuroCurrencyJS = (amount) => {
        const floatAmount = parseFloat(amount);
        let formatted = floatAmount.toFixed(2).replace('.', ','); // Always two decimals first for consistency
        if (formatted.endsWith(',00')) {
            formatted = formatted.slice(0, -3); // Remove ',00' if it ends with '.00'
        }
        return formatted + ' €';
    };

    document.addEventListener('DOMContentLoaded', () => {
        const cartItemsList = document.getElementById('cart-items');
        const cartTotalSpan = document.getElementById('cart-total');
        const checkoutButton = document.getElementById('open-checkout-modal');

        const updateCartDisplay = (cart) => {
            cartItemsList.innerHTML = ''; // Warenkorb leeren
            let total = 0;

            if (Object.keys(cart).length === 0) {
                cartItemsList.innerHTML = '<li id="cart-empty-message">Ihr Warenkorb ist leer.</li>';
                checkoutButton.disabled = true;
            } else {
                checkoutButton.disabled = false;
                // Remove the empty message if it exists
                const existingEmptyMessage = document.getElementById('cart-empty-message');
                if (existingEmptyMessage) {
                    existingEmptyMessage.remove();
                }

                for (const productId in cart) {
                    const item = cart[productId];
                    total += parseFloat(item.price) * parseInt(item.quantity);

                    const listItem = document.createElement('li');
                    listItem.classList.add('cart-item');
                    listItem.dataset.productId = productId;
                    listItem.innerHTML = `
                        <img src="${item.image_url || '/_placeholder.png'}" alt="Bild von ${item.name}: ${item.description}" class="cart-item-image">
                        <div class="cart-item-info">
                            <h4>${item.name}</h4>
                            <p>${formatEuroCurrencyJS(parseFloat(item.price) * parseInt(item.quantity))}</p>
                        </div>
                        <div class="cart-item-controls">
                            <input type="number" class="cart-quantity-input" value="${item.quantity}" min="1" max="${item.stock}" data-product-id="${productId}">
                            <button class="remove-item-btn" data-product-id="${productId}">&times;</button>
                        </div>
                    `;
                    cartItemsList.appendChild(listItem);
                }
            }
            cartTotalSpan.textContent = formatEuroCurrencyJS(total);
        };

        const sendCartUpdateRequest = async (action, productId, quantity = 0) => {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('product_id', productId);
            if (quantity > 0) {
                formData.append('quantity', quantity);
            }

            try {
                const response = await fetch('api/cart_process.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    updateCartDisplay(data.cart);
                    // Optionally show a temporary success message
                } else {
                    alert('Fehler: ' + (data.message || 'Etwas ist schief gelaufen.'));
                }
            } catch (error) {
                console.error('Fetch error:', error);
                alert('Netzwerkfehler beim Aktualisieren des Warenkorbs.');
            }
        };

        // Event Listener für "In den Warenkorb" Buttons
        document.querySelectorAll('.add-to-cart-btn').forEach(button => {
            button.addEventListener('click', () => {
                const productId = button.dataset.productId;
                const quantityInput = button.closest('.product-item').querySelector('.quantity-input');
                const quantity = parseInt(quantityInput.value);

                sendCartUpdateRequest('add', productId, quantity);
            });
        });

        // Event Listener für Mengenänderungen im Produkt-Grid (falls direkt dort geändert wird)
        document.querySelectorAll('.product-controls .quantity-input').forEach(input => {
            input.addEventListener('change', (event) => {
                let value = parseInt(event.target.value);
                const max = parseInt(event.target.max);
                const min = parseInt(event.target.min);

                if (isNaN(value) || value < min) {
                    value = min;
                }
                if (value > max) {
                    value = max;
                }
                event.target.value = value;
            });
        });


        // Event Listener für Mengenänderungen im Warenkorb (Delegation)
        cartItemsList.addEventListener('change', (event) => {
            if (event.target.classList.contains('cart-quantity-input')) {
                const productId = event.target.dataset.productId;
                let quantity = parseInt(event.target.value);
                const max = parseInt(event.target.max);
                const min = parseInt(event.target.min);

                if (isNaN(quantity) || quantity < min) {
                    quantity = min;
                }
                if (quantity > max) {
                    quantity = max;
                }
                event.target.value = quantity; // Update input field with clamped value
                sendCartUpdateRequest('update', productId, quantity);
            }
        });

        // Event Listener für "Entfernen" Buttons im Warenkorb (Delegation)
        cartItemsList.addEventListener('click', (event) => {
            if (event.target.classList.contains('remove-item-btn')) {
                const productId = event.target.dataset.productId;
                sendCartUpdateRequest('remove', productId);
            }
        });

        // --- Checkout Modal Logik ---
        const checkoutModal = document.getElementById('checkoutModal');
        const closeButton = document.querySelector('.close-button');
        const checkoutForm = document.getElementById('checkoutForm');
        const checkoutMessageDiv = document.getElementById('checkout-message');

        checkoutButton.addEventListener('click', () => {
            // Check if cart has items before opening modal
            const currentCartItems = cartItemsList.querySelectorAll('.cart-item');
            if (currentCartItems.length > 0) {
                checkoutModal.style.display = 'flex'; // Use flex to center
                checkoutMessageDiv.style.display = 'none'; // Clear any previous messages
                checkoutMessageDiv.className = 'alert'; // Reset alert classes
            }
        });

        closeButton.addEventListener('click', () => {
            checkoutModal.style.display = 'none';
        });

        window.addEventListener('click', (event) => {
            if (event.target === checkoutModal) {
                checkoutModal.style.display = 'none';
            }
        });

        checkoutForm.addEventListener('submit', async (event) => {
            event.preventDefault(); // Prevent default form submission

            const formData = new FormData(checkoutForm);
            // Append the calculated total amount to the form data
            formData.append('cart_total', parseFloat(cartTotalSpan.textContent.replace(' €', '').replace(',', '.')));


            try {
                const response = await fetch('api/checkout_process.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                checkoutMessageDiv.style.display = 'block';
                if (data.success) {
                    checkoutMessageDiv.classList.add('success');
                    checkoutMessageDiv.classList.remove('error');
                    checkoutMessageDiv.textContent = data.message;
                    checkoutForm.reset(); // Reset form
                    updateCartDisplay({}); // Clear cart display
                    setTimeout(() => {
                        checkoutModal.style.display = 'none';
                        // Optionally reload the page or redirect after successful order
                        // window.location.reload();
                    }, 3000);
                } else {
                    checkoutMessageDiv.classList.add('error');
                    checkoutMessageDiv.classList.remove('success');
                    checkoutMessageDiv.textContent = data.message;
                }
            } catch (error) {
                console.error('Fetch error:', error);
                checkoutMessageDiv.style.display = 'block';
                checkoutMessageDiv.classList.add('error');
                checkoutMessageDiv.classList.remove('success');
                checkoutMessageDiv.textContent = 'Netzwerkfehler: Die Bestellung konnte nicht verarbeitet werden.';
            }
        });

        // Initial cart display update on page load (if items exist in session)
        const initialCart = <?php echo json_encode($_SESSION['cart']); ?>;
        updateCartDisplay(initialCart);

        // Sticky Header Scroll-Effekt
        const header = document.querySelector('header');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 0) {
                header.classList.add('header-scrolled');
            } else {
                header.classList.remove('header-scrolled');
            }
        });
    });
</script>