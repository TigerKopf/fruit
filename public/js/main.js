// public/js/main.js

/**
 * Global application object to encapsulate functions and avoid global namespace pollution.
 */
const App = {
    // --- Initialisierung ---
    init: function() {
        // Stellt sicher, dass das DOM vollständig geladen ist, bevor JavaScript ausgeführt wird
        document.addEventListener('DOMContentLoaded', () => {
            console.log('Frontend JavaScript geladen und DOM bereit.');
            App.bindEventListeners();
            App.loadCartWidget(); // Laden des Warenkorb-Widgets beim Start
        });
    },

    // --- Event Listener registrieren ---
    bindEventListeners: function() {
        // Beispiel: Event Listener für "Zum Warenkorb hinzufügen" Buttons
        // Angenommen, Sie haben Buttons mit der Klasse 'add-to-cart-btn'
        const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');
        addToCartButtons.forEach(button => {
            button.addEventListener('click', App.handleAddToCart);
        });

        // Beispiel: Event Listener für Navigation (falls z.B. mobile Menüs später hinzukommen)
        const navToggle = document.querySelector('.nav-toggle'); // Ein hypothetischer Toggle-Button
        if (navToggle) {
            navToggle.addEventListener('click', App.toggleMobileNav);
        }

        // Fügen Sie hier weitere Event Listener hinzu
    },

    // --- Warenkorb-Logik ---
    /**
     * Behandelt das Hinzufügen eines Produkts zum Warenkorb.
     * Dies ist ein Platzhalter für eine AJAX-Anfrage.
     * @param {Event} event - Das Klick-Event.
     */
    handleAddToCart: async function(event) {
        event.preventDefault(); // Verhindert das Standardverhalten (z.B. Formular-Submit)

        const button = event.target;
        const productId = button.dataset.productId; // Angenommen, der Button hat ein data-product-id Attribut
        const quantity = 1; // Standardmäßig 1 Artikel hinzufügen

        console.log(`Produkt ${productId} wird zum Warenkorb hinzugefügt...`);

        // Hier würde normalerweise eine AJAX-Anfrage an den Server gesendet
        // Beispiel (Pseudo-Code):
        try {
            const response = await fetch('/api/warenkorb/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest' // Signalisiert eine AJAX-Anfrage
                },
                body: JSON.stringify({ productId: productId, quantity: quantity })
            });

            const data = await response.json();

            if (data.success) {
                console.log('Produkt erfolgreich hinzugefügt:', data.message);
                App.updateCartWidget(data.cart); // Warenkorb-Widget aktualisieren
                // Optional: Kurze Bestätigungsnachricht anzeigen (Toast-Benachrichtigung)
            } else {
                console.error('Fehler beim Hinzufügen zum Warenkorb:', data.message);
                // Optional: Fehlermeldung anzeigen
            }
        } catch (error) {
            console.error('Netzwerkfehler beim Hinzufügen zum Warenkorb:', error);
            // Optional: Fehlermeldung anzeigen
        }
    },

    /**
     * Lädt den aktuellen Zustand des Warenkorb-Widgets von Server.
     * Dies könnte beim Seitenaufruf oder nach Aktionen im Warenkorb geschehen.
     */
    loadCartWidget: async function() {
        const cartWidgetElement = document.querySelector('.cart-widget');
        if (!cartWidgetElement) return;

        console.log('Lade Warenkorb-Widget...');
        try {
            const response = await fetch('/api/warenkorb/widget'); // Eine API-Route, die HTML oder JSON für das Widget zurückgibt
            const data = await response.json(); // Oder response.text() wenn es HTML ist

            if (data.success) {
                App.updateCartWidget(data.cart); // Angenommen, data.cart enthält die notwendigen Informationen
            } else {
                console.warn('Warenkorb-Widget konnte nicht geladen werden:', data.message);
                // Ggf. eine leere Warenkorb-Nachricht anzeigen
                cartWidgetElement.innerHTML = `
                    <h3>Warenkorb</h3>
                    <p>Ihr Warenkorb ist derzeit leer.</p>
                `;
            }
        } catch (error) {
            console.error('Fehler beim Laden des Warenkorb-Widgets:', error);
        }
    },

    /**
     * Aktualisiert die Anzeige des Warenkorb-Widgets auf der Seite.
     * @param {object} cartData - Die Daten des Warenkorbs (z.B. {items: [], total: 0}).
     */
    updateCartWidget: function(cartData) {
        const cartItemsContainer = document.querySelector('.cart-items');
        const cartSummaryTotal = document.querySelector('.cart-summary p span');
        const cartWidgetElement = document.querySelector('.cart-widget');

        if (!cartItemsContainer || !cartSummaryTotal || !cartWidgetElement) return;

        cartItemsContainer.innerHTML = ''; // Vorherige Artikel entfernen

        if (cartData && cartData.items && cartData.items.length > 0) {
            cartData.items.forEach(item => {
                const itemHtml = `
                    <div class="cart-item">
                        <img src="${item.image}" alt="${item.name}">
                        <div class="cart-item-details">
                            <h4>${item.name}</h4>
                            <p>${item.quantity} x ${item.price.toFixed(2).replace('.', ',')} &euro;</p>
                        </div>
                    </div>
                `;
                cartItemsContainer.insertAdjacentHTML('beforeend', itemHtml);
            });
            cartSummaryTotal.textContent = `${cartData.total.toFixed(2).replace('.', ',')} €`;

            // Sicherstellen, dass der "Zum Warenkorb" Button sichtbar ist, falls er vorher versteckt war
            const checkoutBtn = document.querySelector('.cart-summary .btn');
            if (checkoutBtn) {
                checkoutBtn.style.display = 'block';
            }
        } else {
            // Warenkorb ist leer
            cartItemsContainer.innerHTML = '<p>Ihr Warenkorb ist leer.</p>';
            cartSummaryTotal.textContent = '0,00 €';
            const checkoutBtn = document.querySelector('.cart-summary .btn');
            if (checkoutBtn) {
                checkoutBtn.style.display = 'none'; // Button ausblenden, wenn leer
            }
        }
        console.log('Warenkorb-Widget aktualisiert:', cartData);
    },

    // --- Beispiel für eine zukünftige mobile Navigation ---
    toggleMobileNav: function() {
        const navMenu = document.querySelector('nav ul');
        if (navMenu) {
            navMenu.classList.toggle('active'); // Fügt/entfernt Klasse 'active'
            // Fügen Sie CSS-Regeln für '.nav ul.active' hinzu, um das Menü anzuzeigen/auszublenden
            console.log('Mobile Navigation getoggelt.');
        }
    }

    // Fügen Sie hier weitere Funktionen hinzu, z.B.
    // - Produktfilterung (AJAX)
    // - Lightbox für Produktbilder
    // - Formularvalidierung
    // - etc.
};

// Startet die Anwendung, sobald das Skript geladen ist
App.init();