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
$pickup_dates = [];
$error_message = '';
$success_message = '';

try {
    // Produkte und Kategorien abrufen
    // Sicherstellen, dass image_url und description auch abgerufen werden
    // GEÄNDERT: Sortierung nach c.category_id ASC und p.product_id ASC
    $stmt = $pdo->query("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.category_id WHERE p.is_active = TRUE ORDER BY c.category_id ASC, p.product_id ASC");
    $all_products = $stmt->fetchAll();

    foreach ($all_products as $product) {
        // Hier wird die Kategorie nach category_name gruppiert, aber die Reihenfolge der Kategorien
        // in der Ausgabe wird durch die ORDER BY Klausel der SQL-Abfrage beeinflusst.
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
                <?php
                // Um die Kategorien tatsächlich nach ID (oder einer impliziten Reihenfolge aus der DB) zu sortieren,
                // und nicht alphabetisch durch den PHP-Schlüssel 'category_name',
                // müsste die $products_by_category Struktur anders aufgebaut werden, z.B.
                // $products_by_category[$product['category_id']]['name'] = $product['category_name'];
                // $products_by_category[$product['category_id']]['products'][] = $product;
                // Dann würde man über die Keys von $products_by_category iterieren, die die IDs wären.
                // Für diese Anfrage belassen wir die Gruppierung nach Name und vertrauen darauf,
                // dass die ursprüngliche Abfrage mit "ORDER BY c.category_id" die Kategorien korrekt einliest.
                // Wenn die Kategorien im Array $products_by_category alphabetisch nach Name sortiert sind,
                // weil PHP-Assoziative Arrays dies tun könnten, dann müsste man das Array explizit neu sortieren.
                // Für PHP ist die Iterationsreihenfolge von foreach() standardmäßig die Einfügungsreihenfolge
                // oder die Reihenfolge nach der letzten Schlüsseländerung, nicht notwendigerweise alphabetisch.
                // Da die DB-Abfrage nach c.category_id sortiert, sollten die Kategorien in der richtigen Reihenfolge auftauchen.
                ?>
                <?php foreach ($products_by_category as $category_name => $products): ?>
                    <section class="category-section">
                        <h2><?php echo htmlspecialchars($category_name); ?></h2>
                        <div class="product-grid">
                            <?php foreach ($products as $product): ?>
                                <div class="product-item">
                                    <img src="<?php echo htmlspecialchars($product['image_url'] ?: '/_placeholder.png'); ?>" alt="Bild von <?php echo htmlspecialchars($product['name']); ?> (<?php echo htmlspecialchars($product['description']); ?>)">
                                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                    <p class="product-quantity-price-line">
                                        <span class="product-unit"><?php echo htmlspecialchars($product['description']); ?> für</span> <span class="product-price-value"><?php echo formatEuroCurrency($product['price']); ?></span>
                                    </p>
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
                                <img src="<?php echo htmlspecialchars($item['image_url'] ?: '/_placeholder.png'); ?>" alt="Bild von <?php echo htmlspecialchars($item['name']); ?>: <?php echo htmlspecialchars($item['description']); ?>" class="cart-item-image">
                                <div class="cart-item-info">
                                    <h4>${item.name}</h4>
                                    <p>${formatEuroCurrencyJS(parseFloat(item.price) * parseInt(item.quantity))}</p>
                                </div>
                                <div class="cart-item-controls">
                                    <input type="number" class="cart-quantity-input" value="${item.quantity}" min="1" max="${item.stock}" data-product-id="${productId}">
                                    <button class="remove-item-btn" data-product-id="${productId}">&times;</button>
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
        const cartSidebar = document.querySelector('.cart-sidebar');
        const cartItemsList = document.getElementById('cart-items');
        const cartTotalSpan = document.getElementById('cart-total');
        const checkoutButton = document.getElementById('open-checkout-modal');
        const mobileCartItemCount = document.getElementById('mobile-cart-item-count');
        const mobileCartTotalSummary = document.getElementById('mobile-cart-total-summary');
        const cartToggleButton = document.getElementById('cart-toggle-mobile');

        // Funktion zur Aktualisierung der Warenkorb-Anzeige
        const updateCartDisplay = (cart) => {
            cartItemsList.innerHTML = ''; // Warenkorb leeren
            let total = 0;
            let itemCount = 0;

            if (Object.keys(cart).length === 0) {
                cartItemsList.innerHTML = '<li id="cart-empty-message">Ihr Warenkorb ist leer.</li>';
                checkoutButton.disabled = true;
            } else {
                checkoutButton.disabled = false;
                const existingEmptyMessage = document.getElementById('cart-empty-message');
                if (existingEmptyMessage) {
                    existingEmptyMessage.remove();
                }

                for (const productId in cart) {
                    const item = cart[productId];
                    total += parseFloat(item.price) * parseInt(item.quantity);
                    itemCount += parseInt(item.quantity);

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
            if (mobileCartItemCount) mobileCartItemCount.textContent = itemCount;
            if (mobileCartTotalSummary) mobileCartTotalSummary.textContent = formatEuroCurrencyJS(total);

            // Deaktiviere Checkout Button, wenn Warenkorb leer
            if (itemCount === 0) {
                checkoutButton.disabled = true;
            } else {
                checkoutButton.disabled = false;
            }
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

        // Funktion, um das Modal zu öffnen
        const openCheckoutModal = () => {
            // Sicherstellen, dass der Warenkorb auf Mobile ausgeklappt ist, bevor das Modal geöffnet wird
            if (window.innerWidth <= 768 && cartSidebar.classList.contains('is-collapsed')) {
                cartSidebar.classList.remove('is-collapsed');
                cartSidebar.classList.add('is-expanded');
            }
            const currentCartItems = cartItemsList.querySelectorAll('.cart-item');
            if (currentCartItems.length > 0) {
                checkoutModal.style.display = 'flex'; // Use flex to center
                checkoutMessageDiv.style.display = 'none'; // Clear any previous messages
                checkoutMessageDiv.className = 'alert'; // Reset alert classes
            }
        };

        // Event Listener für den Checkout-Button im Warenkorb
        checkoutButton.addEventListener('click', openCheckoutModal);
        // Event Listener für den mobilen Checkout-Button (falls vorhanden und sichtbar)
        const mobileCheckoutButton = document.querySelector('.cart-header-mobile + .cart-body-content .checkout-button');
        if (mobileCheckoutButton) {
            mobileCheckoutButton.addEventListener('click', openCheckoutModal);
        }

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

        // --- Mobiler Warenkorb Toggle Logik ---
        const isMobile = () => window.innerWidth <= 768; // Definiere deine Mobile-Breakpoint

        const applyMobileCartState = () => {
            // Zeige/verstecke Desktop/Mobile Warenkorb Header basierend auf Bildschirmgröße
            const desktopHeader = cartSidebar.querySelector('.cart-header-desktop');
            const mobileHeader = cartSidebar.querySelector('.cart-header-mobile');

            // Lese die CSS-Variable für die Höhe aus dem Root-Element
            const rootStyles = getComputedStyle(document.documentElement);
            const mobileCollapsedHeight = parseFloat(rootStyles.getPropertyValue('--header-height-mobile-collapsed'));

            if (isMobile()) {
                if (desktopHeader) desktopHeader.style.display = 'none';
                if (mobileHeader) mobileHeader.style.display = 'flex'; // Mobile Header anzeigen

                // Sicherstellen, dass der Warenkorb zusammengeklappt ist, wenn Mobile-Modus aktiv wird
                if (!cartSidebar.classList.contains('is-expanded')) { // Nur wenn er nicht explizit ausgeklappt wurde
                    cartSidebar.classList.add('is-collapsed');
                    // Setze den Transform-Wert basierend auf der CSS-Variablen
                    cartSidebar.style.transform = `translateY(calc(100% - ${mobileCollapsedHeight}px))`;
                }
            } else {
                if (desktopHeader) desktopHeader.style.display = 'block'; // Desktop Header anzeigen
                if (mobileHeader) mobileHeader.style.display = 'none';

                // Auf Desktop-Größe alle mobilen Klassen entfernen und den normalen Zustand wiederherstellen
                cartSidebar.classList.remove('is-collapsed');
                cartSidebar.classList.remove('is-expanded');
                cartSidebar.style.transform = ''; // Reset transform
            }
        };

        // Event Listener für den mobilen Toggle-Button
        if (cartToggleButton) {
            cartToggleButton.addEventListener('click', () => {
                const rootStyles = getComputedStyle(document.documentElement);
                const mobileCollapsedHeight = parseFloat(rootStyles.getPropertyValue('--header-height-mobile-collapsed'));

                if (cartSidebar.classList.contains('is-collapsed')) {
                    cartSidebar.classList.remove('is-collapsed');
                    cartSidebar.classList.add('is-expanded');
                    cartSidebar.style.transform = 'translateY(0)'; // Ausgeklappt
                } else {
                    cartSidebar.classList.remove('is-expanded');
                    cartSidebar.classList.add('is-collapsed');
                    cartSidebar.style.transform = `translateY(calc(100% - ${mobileCollapsedHeight}px))`; // Zusammengeklappt
                }
            });
        }

        // Zustand bei Seitenladung und Größenänderung anwenden
        applyMobileCartState();
        window.addEventListener('resize', applyMobileCartState);


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