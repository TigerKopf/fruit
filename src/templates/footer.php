<?php
// templates/footer.php
// Dieser Footer wird durch index.php eingebunden
?>

</div> <!-- Schließt den #page-content-wrapper -->

<footer class="main-footer">
    <div class="footer-content-wrapper">
        <div class="footer-container">
            <div class="footer-section contact-info">
                <h3>Früchte aus Portugal</h3>
                <p>© <?php echo date("Y"); ?> Früchte aus Portugal. Alle Rechte vorbehalten.</p>
                <p>Rosental 1, 53332 Bornheim</p>
                <p>E-Mail: <a href="mailto:info@früch.de">info@früch.de</a></p>
            </div>
            <div class="footer-section navigation">
                <h3>Navigation</h3>
                <ul>
                    <li><a href="/">Home</a></li>
                    <li><a href="/produkte">Produkte</a></li>
                    <li><a href="/geschichte">Geschichte</a></li>
                    <li><a href="/kontakt">Kontakt</a></li>
                    <li><a href="/warenkorb">Warenkorb</a></li>
                </ul>
            </div>
            <div class="footer-section legal">
                <h3>Rechtliches</h3>
                <ul>
                    <li><a href="/impressum">Impressum</a></li>
                    <li><a href="/datenschutz">Datenschutz</a></li>
                    <li><a href="/agb">AGB</a></li>
                    <li><a href="/widerrufsrecht">Widerrufsrecht</a></li>
                </ul>
            </div>
            <div class="footer-section social">
                <h3>Folge uns</h3>
                <div class="social-icons">
                    <a href="https://www.efbornheim.de/" target="_blank" aria-label="Webseite"><img src="/-webseite.png" alt="Webseite" loading="lazy" decoding="async"></a>
                    <a href="https://chat.whatsapp.com/BaBd04yeoGvDkcGZcRNkTI?mode=ac_t" target="_blank" aria-label="WhatsApp"><img src="/-WhatsApp.png" alt="WhatsApp" loading="lazy" decoding="async"></a>
                    <a href="https://www.youtube.com/@efbornheim" target="_blank" aria-label="Youtube"><img src="/-youtube.png" alt="Youtube" loading="lazy" decoding="async"></a>
                    <a href="https://www.instagram.com/efbornheim/" target="_blank" aria-label="Twitter"><img src="/-instagram.png" alt="Instagram" loading="lazy" decoding="async"></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            Made with ❤️ in Germany
        </div>
    </div>
</footer>

<div id="checkoutModal" class="modal">
    <div class="modal-content">
        <span class="close-button">×</span>
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
                <div class="payment-options-wrapper">
                    <label class="radio-option prefer-payment" for="paymentBankTransfer">
                        <input type="radio" id="paymentBankTransfer" name="paymentMethod" value="bank_transfer" required checked>
                        Überweisung
                    </label>
                    <label class="radio-option discourage-payment" for="paymentCash">
                        <input type="radio" id="paymentCash" name="paymentMethod" value="cash" required>
                        Barzahlung bei Abholung
                    </label>
                </div>
                <p class="payment-info-text">
                    <strong>Überweisung wird bevorzugt!</strong> Wir empfehlen die Überweisung, um einen reibungslosen und schnellen Bestellablauf zu gewährleisten und unsere ehrenamtliche Arbeit zu vereinfachen.
                </p>
            </div>

            <div class="form-group">
                <label for="pickupDate">Abholtermin:</label>
                <select id="pickupDate" name="pickupDate" required>
                    <option value="">Bitte wählen</option>
                    <?php
                    global $pdo;
                    if (!isset($pdo)) {
                        require_once ROOT_PATH . 'include/db.php';
                        $pdo = getDbConnection();
                    }
                    $pickup_dates_for_modal = [];
                    try {
                        $stmt_dates_modal = $pdo->query("SELECT * FROM pickup_dates WHERE is_active = TRUE AND pickup_datetime >= NOW() ORDER BY pickup_datetime ASC");
                        $pickup_dates_for_modal = $stmt_dates_modal->fetchAll();
                    } catch (PDOException $e) {
                        error_log("Datenbankfehler beim Laden der Abholtermine im Modal: " . $e->getMessage());
                    }
                    foreach ($pickup_dates_for_modal as $date): ?>
                        <option value="<?php echo htmlspecialchars($date['pickup_date_id']); ?>">
                            <?php echo (new DateTime($date['pickup_datetime']))->format('d.m.Y H:i') . ' Uhr'; ?> - <?php echo htmlspecialchars($date['location']); ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if (empty($pickup_dates_for_modal)): ?>
                        <option value="" disabled>Keine Abholtermine verfügbar</option>
                    <?php endif; ?>
                </select>
            </div>

            <button type="submit" id="checkout-submit-button">Bestellung abschicken</button>
        </form>

        <div id="checkout-loading-overlay" class="loading-overlay" style="display: none;">
            <div class="spinner"></div>
            <p>Bestellung wird verarbeitet...</p>
        </div>

    </div>
</div>

<script>
    const formatEuroCurrencyJS = (amount) => {
        const floatAmount = parseFloat(amount);
        let formatted = floatAmount.toFixed(2).replace('.', ',');
        if (formatted.endsWith(',00')) {
            formatted = formatted.slice(0, -3);
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

        const checkoutModal = document.getElementById('checkoutModal');
        const closeButton = checkoutModal.querySelector('.close-button');
        const checkoutForm = checkoutModal.querySelector('#checkoutForm');
        const checkoutMessageDiv = checkoutModal.querySelector('#checkout-message');
        const pickupDateSelect = checkoutModal.querySelector('#pickupDate');
        const checkoutLoadingOverlay = checkoutModal.querySelector('#checkout-loading-overlay');


        // --- Event Listener für das Modal (global, da Modal jetzt im Footer ist) ---
        closeButton.addEventListener('click', () => {
            checkoutModal.style.display = 'none';
            document.body.style.overflow = '';
            checkoutLoadingOverlay.style.display = 'none';
        });

        window.addEventListener('click', (event) => {
            if (event.target === checkoutModal) {
                checkoutModal.style.display = 'none';
                document.body.style.overflow = '';
                checkoutLoadingOverlay.style.display = 'none';
            }
        });

        window.openCheckoutModal = () => {
            if (!checkoutButton.disabled) {
                if (window.innerWidth <= 768 && cartSidebar && cartSidebar.classList.contains('is-collapsed')) {
                    cartSidebar.classList.remove('is-collapsed');
                    cartSidebar.classList.add('is-expanded');
                }
                const currentCartItems = cartItemsList.querySelectorAll('.cart-item');
                if (currentCartItems.length > 0) {
                    checkoutModal.style.display = 'flex';
                    checkoutMessageDiv.style.display = 'none';
                    checkoutMessageDiv.className = 'alert';
                    document.body.style.overflow = 'hidden';
                    checkoutLoadingOverlay.style.display = 'none'; // Immer versteckt beim Öffnen
                } else {
                    checkoutModal.style.display = 'flex';
                    checkoutLoadingOverlay.style.display = 'none';
                    checkoutMessageDiv.classList.add('error');
                    checkoutMessageDiv.classList.remove('success');
                    checkoutMessageDiv.textContent = 'Ihr Warenkorb ist leer. Bitte fügen Sie Produkte hinzu.';
                    checkoutMessageDiv.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                }
            }
        };

        if (checkoutButton) {
            checkoutButton.addEventListener('click', window.openCheckoutModal);
        }
        const mobileCheckoutButton = document.querySelector('.cart-header-mobile + .cart-body-content .checkout-button');
        if (mobileCheckoutButton) {
            mobileCheckoutButton.addEventListener('click', window.openCheckoutModal);
        }

        checkoutForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (cartItemsList.querySelectorAll('.cart-item').length === 0) {
                 checkoutLoadingOverlay.style.display = 'none';
                 checkoutMessageDiv.style.display = 'block';
                 checkoutMessageDiv.classList.add('error');
                 checkoutMessageDiv.classList.remove('success');
                 checkoutMessageDiv.textContent = 'Ihr Warenkorb ist leer. Bitte fügen Sie Produkte hinzu.';
                 return;
            }

            checkoutLoadingOverlay.style.display = 'flex';
            checkoutMessageDiv.style.display = 'none';


            const formData = new FormData(checkoutForm);
            const cartTotalSpanInShop = document.getElementById('cart-total');
            formData.append('cart_total', parseFloat(cartTotalSpanInShop.textContent.replace(' €', '').replace(',', '.')));


            try {
                const response = await fetch('api/checkout_process.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'include' 
                });
                const data = await response.json();

                if (data.success) {
                    window.location.href = '/success';
                } else {
                    checkoutLoadingOverlay.style.display = 'none';
                    checkoutMessageDiv.style.display = 'block';
                    checkoutMessageDiv.classList.add('error');
                    checkoutMessageDiv.classList.remove('success');
                    checkoutMessageDiv.textContent = data.message;
                }
            } catch (error) {
                console.error('Fetch error:', error);
                checkoutLoadingOverlay.style.display = 'none';
                checkoutMessageDiv.style.display = 'block';
                checkoutMessageDiv.classList.add('error');
                checkoutMessageDiv.classList.remove('success');
                checkoutMessageDiv.textContent = 'Netzwerkfehler: Die Bestellung konnte nicht verarbeitet werden.';
            }
        });

        window.updateCartDisplay = (cart) => {
            cartItemsList.innerHTML = '';
            let total = 0;
            let itemCount = 0;

            if (Object.keys(cart).length === 0) {
                cartItemsList.innerHTML = '<li id="cart-empty-message">Ihr Warenkorb ist leer.</li>';
                checkoutButton.disabled = true;
                const checkoutSubmitButton = checkoutModal.querySelector('#checkout-submit-button');
                if (checkoutSubmitButton) checkoutSubmitButton.disabled = true;
            } else {
                checkoutButton.disabled = false;
                const checkoutSubmitButton = checkoutModal.querySelector('#checkout-submit-button');
                if (checkoutSubmitButton) checkoutSubmitButton.disabled = false;

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
                        <img src="${item.image_url || '/_placeholder.png'}" alt="Bild von ${item.name}: ${item.description}" class="cart-item-image" loading="lazy" decoding="async">
                        <div class="cart-item-info">
                            <h4>${item.name}</h4>
                            <p>${formatEuroCurrencyJS(parseFloat(item.price) * parseInt(item.quantity))}</p>
                        </div>
                        <div class="cart-item-controls">
                            <input type="number" class="cart-quantity-input" value="${item.quantity}" min="1" max="${item.stock}" data-product-id="${productId}">
                            <button class="remove-item-btn" data-product-id="${productId}">×</button>
                        </div>
                    `;
                    cartItemsList.appendChild(listItem);
                }
            }
            cartTotalSpan.textContent = formatEuroCurrencyJS(total);
            if (mobileCartItemCount) mobileCartItemCount.textContent = itemCount;
            if (mobileCartTotalSummary) mobileCartTotalSummary.textContent = formatEuroCurrencyJS(total);

            if (itemCount === 0) {
                checkoutButton.disabled = true;
            } else {
                checkoutButton.disabled = false;
            }
        };

        window.sendCartUpdateRequest = async (action, productId, quantity = 0) => {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('product_id', productId);
            if (quantity > 0) {
                formData.append('quantity', quantity);
            }

            try {
                const response = await fetch('api/cart_process.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'include'
                });
                const data = await response.json();
                if (data.success) {
                    window.updateCartDisplay(data.cart);
                } else {
                    alert('Fehler: ' + (data.message || 'Etwas ist schief gelaufen.'));
                }
            } catch (error) {
                console.error('Fetch error:', error);
                alert('Netzwerkfehler beim Aktualisieren des Warenkorbs.');
            }
        };


        const pageContentWrapper = document.getElementById('page-content-wrapper');

        if (pageContentWrapper) {
            pageContentWrapper.addEventListener('click', (event) => {
                if (event.target.classList.contains('add-to-cart-btn')) {
                    const button = event.target;
                    const productId = button.dataset.productId;
                    const quantityInput = button.closest('.product-item').querySelector('.quantity-input');
                    const quantity = parseInt(quantityInput.value);
                    window.sendCartUpdateRequest('add', productId, quantity);
                }

                if (event.target.classList.contains('remove-item-btn')) {
                    const productId = event.target.dataset.productId;
                    window.sendCartUpdateRequest('remove', productId);
                }
            });

            pageContentWrapper.addEventListener('change', (event) => {
                if (event.target.classList.contains('quantity-input') && event.target.closest('.product-item')) {
                    let value = parseInt(event.target.value);
                    const max = parseInt(event.target.max);
                    const min = parseInt(event.target.min);

                    if (isNaN(value) || value < min) value = min;
                    if (value > max) value = max;
                    event.target.value = value;
                }

                if (event.target.classList.contains('cart-quantity-input')) {
                    const productId = event.target.dataset.productId;
                    let quantity = parseInt(event.target.value);
                    const max = parseInt(event.target.max);
                    const min = parseInt(event.target.min);

                    if (isNaN(quantity) || quantity < min) quantity = min;
                    if (quantity > max) quantity = max;
                    event.target.value = quantity;
                    window.sendCartUpdateRequest('update', productId, quantity);
                }
            });
        }


        const initialCart = <?php echo json_encode($_SESSION['cart']); ?>;
        window.updateCartDisplay(initialCart);

        const isMobile = () => window.innerWidth <= 768;

        // GEÄNDERT: Logik für das Auf- und Zuklappen des mobilen Warenkorbs
        const applyMobileCartState = () => {
            if (isMobile() && cartSidebar) {
                // Standardmäßig zusammengeklappt
                cartSidebar.classList.add('is-collapsed');
                cartSidebar.classList.remove('is-expanded');
            } else if (cartSidebar) {
                // Desktop-Ansicht: Klassen entfernen
                cartSidebar.classList.remove('is-collapsed', 'is-expanded');
            }
        };

        if (cartToggleButton) {
            cartToggleButton.addEventListener('click', () => {
                const isCollapsed = cartSidebar.classList.contains('is-collapsed');
                if (isCollapsed) {
                    cartSidebar.classList.remove('is-collapsed');
                    cartSidebar.classList.add('is-expanded');
                    document.body.style.overflow = 'hidden'; // Scrollen des Hintergrunds sperren
                } else {
                    cartSidebar.classList.remove('is-expanded');
                    cartSidebar.classList.add('is-collapsed');
                    document.body.style.overflow = ''; // Scrollen wieder erlauben
                }
            });
        }

        applyMobileCartState();
        window.addEventListener('resize', applyMobileCartState);
    });
</script>
</body>
</html>