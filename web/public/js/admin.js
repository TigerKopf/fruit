// public/js/admin.js

/**
 * Global application object for the admin panel to encapsulate functions.
 */
const AdminApp = {
    // --- Initialisierung ---
    init: function() {
        document.addEventListener('DOMContentLoaded', () => {
            console.log('Admin JavaScript geladen und DOM bereit.');
            AdminApp.bindEventListeners();
            AdminApp.loadDashboardData(); // Daten für das Dashboard laden
            AdminApp.initDynamicTables(); // Dynamische Tabellen initialisieren (z.B. für Filter/Sortierung)
        });
    },

    // --- Event Listener registrieren ---
    bindEventListeners: function() {
        // Beispiel: Formular-Submission für Produktbearbeitung
        const productForms = document.querySelectorAll('form.product-form');
        productForms.forEach(form => {
            form.addEventListener('submit', AdminApp.handleProductFormSubmit);
        });

        // Beispiel: Löschen-Buttons mit Bestätigung
        const deleteButtons = document.querySelectorAll('.btn-delete-confirm');
        deleteButtons.forEach(button => {
            button.addEventListener('click', AdminApp.confirmDeletion);
        });

        // Beispiel: Change-Event für Status-Dropdowns in Bestellungen
        const orderStatusSelects = document.querySelectorAll('.order-status-select');
        orderStatusSelects.forEach(select => {
            select.addEventListener('change', AdminApp.updateOrderStatus);
        });

        // Fügen Sie hier weitere spezifische Admin-Event-Listener hinzu
    },

    // --- Dashboard-Logik ---
    /**
     * Lädt dynamische Daten für das Admin-Dashboard (z.B. Statistiken, neueste Bestellungen).
     * Dies ist ein Platzhalter für eine AJAX-Anfrage.
     */
    loadDashboardData: async function() {
        const dashboardStatsContainer = document.getElementById('dashboard-stats');
        if (!dashboardStatsContainer) return;

        console.log('Lade Dashboard-Daten...');
        try {
            const response = await fetch('/api/admin/dashboard-stats'); // API-Endpunkt für Dashboard-Daten
            const data = await response.json();

            if (data.success) {
                // Beispiel: Metriken aktualisieren
                document.getElementById('total-orders-value').textContent = data.stats.totalOrders;
                document.getElementById('total-customers-value').textContent = data.stats.totalCustomers;
                document.getElementById('total-revenue-value').textContent = data.stats.totalRevenue.toFixed(2).replace('.', ',') + ' €';

                // Optional: Neueste Bestellungen aktualisieren (falls im Dashboard vorhanden)
                // AdminApp.updateLatestOrders(data.stats.latestOrders);

                console.log('Dashboard-Daten erfolgreich geladen:', data.stats);
            } else {
                console.error('Fehler beim Laden der Dashboard-Daten:', data.message);
            }
        } catch (error) {
            console.error('Netzwerkfehler beim Laden der Dashboard-Daten:', error);
        }
    },

    // --- Formular-Handling ---
    /**
     * Behandelt die Submission von Produktformularen via AJAX.
     * @param {Event} event - Das Submit-Event.
     */
    handleProductFormSubmit: async function(event) {
        event.preventDefault(); // Verhindert den Standard-Formular-Submit

        const form = event.target;
        const formData = new FormData(form); // Erstellt FormData aus dem Formular
        const productId = form.dataset.productId; // Für Edit-Modus
        const url = productId ? `/api/admin/products/update/${productId}` : '/api/admin/products/add';
        const method = productId ? 'POST' : 'POST'; // Oder 'PUT'/'PATCH' für RESTful APIs

        console.log(`Produkt-Formular gesendet an: ${url}`);

        try {
            const response = await fetch(url, {
                method: method,
                body: formData // FormData wird automatisch als 'multipart/form-data' gesendet
                // Headers wie 'Content-Type' sind hier bei FormData nicht notwendig
            });

            const data = await response.json();

            if (data.success) {
                console.log('Produkt erfolgreich gespeichert:', data.message);
                AdminApp.showMessage('Produkt erfolgreich gespeichert!', 'success');
                // Optional: Weiterleitung zur Produktliste
                if (!productId) { // Nur bei neuem Produkt direkt weiterleiten
                   window.location.href = '/admin/produkte';
                }
            } else {
                console.error('Fehler beim Speichern des Produkts:', data.message);
                AdminApp.showMessage('Fehler: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Netzwerkfehler beim Speichern des Produkts:', error);
            AdminApp.showMessage('Netzwerkfehler beim Speichern des Produkts.', 'error');
        }
    },

    // --- Löschen-Bestätigung ---
    /**
     * Zeigt eine Bestätigungsabfrage vor dem Löschen eines Eintrags.
     * @param {Event} event - Das Klick-Event des Löschen-Buttons.
     */
    confirmDeletion: function(event) {
        event.preventDefault();
        const button = event.target.closest('.btn-delete-confirm'); // Findet den Button
        const entityId = button.dataset.id;
        const entityType = button.dataset.type || 'Eintrag'; // Z.B. 'Produkt', 'Kunde'
        const deleteUrl = button.href; // Oder ein data-delete-url Attribut

        if (confirm(`Sind Sie sicher, dass Sie diesen ${entityType} (ID: ${entityId}) wirklich löschen möchten? Diese Aktion kann nicht rückgängig gemacht werden.`)) {
            // Optional: Wenn es ein Formular-Submit sein soll (POST-Request)
            // AdminApp.performDelete(deleteUrl, entityId);
            // Für einen direkten Link-Click (GET-Request):
            window.location.href = deleteUrl;
        }
    },

    /**
     * Führt eine AJAX-Löschaktion aus (optional, wenn nicht über direkten Link gelöscht wird).
     * @param {string} url - Die URL für die DELETE-Anfrage.
     * @param {string} id - Die ID des zu löschenden Eintrags.
     */
    performDelete: async function(url, id) {
        try {
            const response = await fetch(url, {
                method: 'POST', // Oft wird DELETE über POST mit einem _method-Feld simuliert
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ _method: 'DELETE', id: id })
            });
            const data = await response.json();

            if (data.success) {
                AdminApp.showMessage('Löschen erfolgreich!', 'success');
                // Optional: Zeile aus Tabelle entfernen oder Seite neu laden
                setTimeout(() => window.location.reload(), 1000);
            } else {
                AdminApp.showMessage('Fehler beim Löschen: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Netzwerkfehler beim Löschen:', error);
            AdminApp.showMessage('Netzwerkfehler beim Löschen.', 'error');
        }
    },


    // --- Bestellstatus aktualisieren ---
    /**
     * Aktualisiert den Bestellstatus über eine AJAX-Anfrage.
     * @param {Event} event - Das Change-Event des Select-Feldes.
     */
    updateOrderStatus: async function(event) {
        const select = event.target;
        const orderId = select.dataset.orderId;
        const newStatus = select.value;

        console.log(`Bestellung ${orderId}: Status wird auf ${newStatus} aktualisiert...`);

        try {
            const response = await fetch(`/api/admin/orders/update-status/${orderId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ status: newStatus })
            });
            const data = await response.json();

            if (data.success) {
                AdminApp.showMessage('Bestellstatus erfolgreich aktualisiert!', 'success');
                // Optional: UI-Update, z.B. Hintergrundfarbe der Zeile ändern
            } else {
                console.error('Fehler beim Aktualisieren des Bestellstatus:', data.message);
                AdminApp.showMessage('Fehler beim Aktualisieren des Bestellstatus: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Netzwerkfehler beim Aktualisieren des Bestellstatus:', error);
            AdminApp.showMessage('Netzwerkfehler beim Aktualisieren des Bestellstatus.', 'error');
        }
    },

    // --- Allgemeine Hilfsfunktionen ---
    /**
     * Initialisiert dynamische Tabellen (Platzhalter für Bibliotheken wie DataTables.js oder eigene Sortier-/Filterlogik).
     */
    initDynamicTables: function() {
        const tables = document.querySelectorAll('.data-table');
        tables.forEach(table => {
            // Hier könnten Sie Logik für Sortierung, Filterung oder Paginierung hinzufügen.
            // Z.B. Event Listener für Klicks auf Tabellenüberschriften für Sortierung.
            console.log('Dynamische Tabelle initialisiert:', table.id || table.className);
        });
    },

    /**
     * Zeigt eine temporäre Benachrichtigungsnachricht an (Toast-Nachricht).
     * Sie müssten CSS für '.toast-message' in admin.css definieren.
     * @param {string} message - Die anzuzeigende Nachricht.
     * @param {string} type - Der Typ der Nachricht ('success', 'error', 'info').
     */
    showMessage: function(message, type = 'info') {
        const toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            // Container erstellen, falls nicht vorhanden (einmalig)
            const newContainer = document.createElement('div');
            newContainer.id = 'toast-container';
            document.body.appendChild(newContainer);
            toastContainer = newContainer;
        }

        const toast = document.createElement('div');
        toast.className = `toast-message toast-${type}`;
        toast.textContent = message;
        toastContainer.appendChild(toast);

        // Nach X Sekunden ausblenden und entfernen
        setTimeout(() => {
            toast.classList.add('fade-out');
            toast.addEventListener('transitionend', () => toast.remove());
        }, 3000); // 3 Sekunden anzeigen
    }

    // Fügen Sie hier weitere Funktionen hinzu, z.B.
    // - Client-seitige Formularvalidierung
    // - Bild-Upload-Vorschau
    // - Drag-and-Drop für Produktbilder
    // - etc.
};

// Startet die Admin-Anwendung, sobald das Skript geladen ist
AdminApp.init();

/*
// Beispiel CSS für Toast-Nachrichten (in admin.css hinzufügen):
#toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.toast-message {
    background-color: #3c4043;
    color: #e8eaed;
    padding: 12px 20px;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    opacity: 1;
    transition: opacity 0.5s ease-out, transform 0.3s ease-out;
    transform: translateX(0);
    min-width: 250px;
}

.toast-message.fade-out {
    opacity: 0;
    transform: translateX(100%);
}

.toast-success {
    background-color: #34a853; // Google Green
}

.toast-error {
    background-color: #dc3545; // Google Red
}

.toast-info {
    background-color: #1a73e8; // Google Blue
}
*/