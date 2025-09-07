<?php
// modules/success.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Holen der Session-Nachrichten und -Daten
$success_message = $_SESSION['checkout_success_message'] ?? '';
unset($_SESSION['checkout_success_message']);

$email_status_message = $_SESSION['checkout_email_status_message'] ?? '';
unset($_SESSION['checkout_email_status_message']);

$orderId = $_SESSION['last_order_id'] ?? null;
$orderDetails = $_SESSION['last_order_details'] ?? null;
$pickupInfo = $_SESSION['last_pickup_info'] ?? null;
$paymentInfo = $_SESSION['last_payment_info'] ?? null;
$customerInfo = $_SESSION['customer_info'] ?? null;

// Unsetten, um sicherzustellen, dass sie bei erneutem direkten Aufruf leer sind
unset($_SESSION['last_order_id']);
unset($_SESSION['last_order_details']);
unset($_SESSION['last_pickup_info']);
unset($_SESSION['last_payment_info']);
unset($_SESSION['customer_info']);


// Robuste Fallback-Prüfung: Wenn keine vollständigen Daten vorhanden sind, aktiviere den Fallback-Modus
$fallback_mode = false;
if (!$orderId || !$orderDetails || !$pickupInfo || !$paymentInfo || !$customerInfo) {
    error_log("FEHLER: Session-Daten für Bestellbestätigungsseite fehlen. Möglicherweise direkter Aufruf oder Session-Verlust.");
    $fallback_mode = true;
    $error_message_header = "Bestelldetails konnten leider nicht geladen werden.";
    if (empty($orderId)) {
        $error_message_header .= " Es konnte keine Bestellnummer ermittelt werden.";
    } else {
        $error_message_header .= " Ihre Bestellnummer war eventuell: " . htmlspecialchars($orderId) . ".";
    }
    $error_message_header .= " Bitte kontaktieren Sie uns umgehend unter <a href='mailto:info@früch.de'>info@früch.de</a> mit dem Betreff 'Fehlende Bestellbestätigung' (referenziert Bestellung Nr. " . ($orderId ? htmlspecialchars($orderId) : 'N/A') . ").";
}


// Hilfsfunktion zur Formatierung
if (!function_exists('formatEuroCurrency')) {
    function formatEuroCurrency(float $amount): string {
        if (fmod($amount, 1.0) == 0) {
            return number_format($amount, 0, ',', '.') . ' €';
        } else {
            return number_format($amount, 2, ',', '.') . ' €';
        }
    }
}

// Konfigurationsdetails für die Überweisung (idealerweise aus einer Konfigurationsdatei oder DB)
// Für dieses Beispiel als feste Strings, aber in Realität dynamisch holen
$bank_account_holder = "Früchte aus Portugal"; // Oder dein richtiger Name
$bank_iban = "DE12 3456 7890 1234 5678 90"; // Dummy-IBAN, ANPASSEN!
$bank_bic = "DUMMYBICXXX"; // Dummy-BIC, ANPASSEN!
?>

<!-- success-page-wrapper umgibt die Inhalte der Success-Seite innerhalb von <main> -->
<div class="success-page-wrapper">
    <div class="success-header">
        <h1>Ihre Bestellung ist unterwegs!</h1>
        <?php if (!$fallback_mode && !empty($success_message)): ?>
            <div class="alert success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php elseif ($fallback_mode): ?>
             <div class="alert error fallback-alert">
                <?php echo $error_message_header; // HTML-Tags hier erlaubt ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="success-sections-container">

        <!-- Erster Block: Zahlung & Kontakt / QR-Code -->
        <section class="success-info-card-group">
            <div class="success-info-card payment-info">
                <h2>Zahlung & Abholung</h2>
                <?php if ($fallback_mode): ?>
                    <div class="alert error fallback-details-alert">
                        <p>Detaillierte Zahlungs- und Abholinformationen sind derzeit nicht verfügbar. Bitte prüfen Sie Ihre E-Mails oder kontaktieren Sie uns.</p>
                        <p>Ihre Bestellnummer war eventuell: <strong><?php echo htmlspecialchars($orderId ?? 'N/A'); ?></strong>.</p>
                    </div>
                <?php else: ?>
                    <h3>Zahlung: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $paymentInfo['payment_method']))); ?></h3>
                    <!-- GEÄNDERT: "Gesamtbetrag" P-Tag entfernt -->
                    <?php if ($paymentInfo['payment_method'] === 'bank_transfer'): ?>
                        <div class="payment-details bank-transfer">
                            <p class="details-intro">Bitte überweisen Sie den Betrag von <strong class="highlight-total"><?php echo formatEuroCurrency($orderDetails['total_amount']); ?></strong> unter Angabe des Verwendungszwecks:</p>
                            <ul class="bank-details-list">
                                <li class="detail-item copy-to-clipboard">
                                    <span class="detail-label">Empfänger:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($bank_account_holder); ?></span>
                                </li>
                                <li class="detail-item copy-to-clipboard">
                                    <span class="detail-label">Verwendungszweck:</span>
                                    <strong class="detail-value reference-number"><?php echo htmlspecialchars($paymentInfo['transaction_id'] ?? 'N/A'); ?></strong>
                                </li>
                                <li class="detail-item copy-to-clipboard">
                                    <span class="detail-label">IBAN:</span>
                                    <strong class="detail-value iban-value"><?php echo htmlspecialchars($bank_iban); ?></strong>
                                </li>
                                <li class="detail-item copy-to-clipboard">
                                    <span class="detail-label">BIC:</span>
                                    <span class="detail-value bic-value"><?php echo htmlspecialchars($bank_bic); ?></span>
                                </li>
                            </ul>
                            <p class="processing-note">Ihre Bestellung Nr. <strong><?php echo htmlspecialchars($orderDetails['order_id'] ?? 'N/A'); ?></strong> wird nach Zahlungseingang bearbeitet. Eine Versandbestätigung erhalten Sie separat.</p>
                        </div>
                    <?php else: // Barzahlung ?>
                        <div class="payment-details cash-on-pickup">
                            <p class="details-intro">Bezahlen Sie bequem <strong class="highlight-total"><?php echo formatEuroCurrency($orderDetails['total_amount']); ?></strong> bei Abholung Ihrer Bestellung Nr. <strong><?php echo htmlspecialchars($orderDetails['order_id'] ?? 'N/A'); ?></strong>.</p>
                        </div>
                    <?php endif; ?>

                    <h3>Abholung</h3>
                    <div class="pickup-details">
                        <p>Bereit zur Abholung am:</p>
                        <p class="pickup-datetime"><strong><?php echo (new DateTime($pickupInfo['pickup_datetime']))->format('d.m.Y H:i') . ' Uhr'; ?></strong></p>
                        <p class="pickup-location">Ort: <strong><?php echo htmlspecialchars($pickupInfo['location']); ?></strong></p>
                        <?php if (!empty($email_status_message)): ?>
                            <div class="alert error" style="margin-top: var(--spacing-md);">
                                <?php echo htmlspecialchars($email_status_message); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; /* Ende des if (!fallback_mode) für Zahlungs- & Abholdetails */ ?>
            </div>

            <div class="success-info-card whatsapp-qr">
                <h2>Kontakt & Updates</h2>
                <p class="whatsapp-intro">Verpassen Sie keine Neuigkeiten! Treten Sie unserer WhatsApp-Gruppe bei für Echtzeit-Updates zu Ihrer Bestellung, neuen Produkten und wichtigen Abholinformationen.</p>
                <div class="qr-code-section"> <!-- GEÄNDERT: Neue Sektion für QR und Button -->
                    <div class="qr-code-container">
                        <!-- Annahme: QR-Code Bild liegt als /_whatsapp-qr.svg im assets-img-Ordner -->
                        <img src="/-whatsapp-qr.svg" alt="WhatsApp Gruppen QR Code" class="qr-code-img" loading="lazy" decoding="async">
                        <a href="https://chat.whatsapp.com/BaBd04yeoGvDkcGZcRNkTI" target="_blank" class="whatsapp-button hero-button">
                            <!-- SVG Icon ist jetzt direkt hier und kein php-generated HTML -->
                            <svg class="whatsapp-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M12.04 2C7.34 2 3.58 5.76 3.58 10.46c0 1.54.4 3.01 1.09 4.31L3.02 21.01l6.19-1.63a8.557 8.557 0 005.15 1.63c4.7 0 8.46-3.76 8.46-8.46S16.74 2 12.04 2zM17 15.6c-.19.34-.84.66-1.16.71-.31.05-.69.04-1.04-.08a10.875 10.875 0 01-5.71-3.69c-.31-.4-.69-.93-.69-1.29s.21-.49.33-.62c.1-.11.23-.29.35-.39.11-.11.16-.1.29.08.13.19.82 2 1.34 2.37.52.37.89.43 1.2.14.33-.3.49-.6.64-.81s.49-1.28.66-1.7.35-.74.45-.71.25.1.5.25c.23.14.93.43 1.08.52.14.1.25.16.27.27-.01.1-.09.33-.18.52z"/></svg>
                            WhatsApp Gruppe beitreten
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Zweiter Block: Bestellzusammenfassung -->
        <section class="success-order-details-card">
            <h2>Ihre Bestellung #<?php echo htmlspecialchars($orderId ?? 'N/A'); ?></h2>
            <a href="mailto:info@früch.de?subject=Frage%20zu%20Bestellung%20%23<?php echo urlencode($orderId ?? 'N/A'); ?>" class="edit-order-link" aria-label="Bestellung ändern oder nachfragen">
                <span class="edit-order-text">Anfrage / Bearbeiten</span>
                <svg class="edit-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
            </a>
            <?php if ($fallback_mode): ?>
                <div class="alert error fallback-details-alert">
                    <p>Details zur Bestellung können nicht angezeigt werden.</p>
                </div>
            <?php elseif (isset($orderDetails) && !empty($orderDetails['items'])): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Produkt</th>
                            <th>Menge</th>
                            <th>Einzelpreis</th>
                            <th>Summe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderDetails['items'] as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                <td><?php echo formatEuroCurrency($item['price_at_order']); ?></td>
                                <td><?php echo formatEuroCurrency($item['quantity'] * $item['price_at_order']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3"><strong>Gesamtsumme:</strong></td>
                            <td><strong><?php echo formatEuroCurrency($orderDetails['total_amount']); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            <?php else: ?>
                <div class="alert error fallback-details-alert">
                    <p>Bestellartikel konnten nicht geladen werden.</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Dritter Block: Mission / Projektinformationen -->
        <section class="success-mission-card">
            <h2>Ihre Hilfe macht einen Unterschied!</h2>
            <p>Mit Ihrer Bestellung unterstützen Sie direkt unsere Missionsarbeit in Portugal. <strong>100% des Gewinnes</strong> aus dem Verkauf dieser Früchte fließt in Projekte, die Not lindern und Menschen eine Perspektive geben.</p>
            <p>Jeder Bissen dieser sonnengereiften Früchte hilft, Gutes zu tun und positive Veränderungen in der Welt zu bewirken.</p>
            <p>Vielen Dank für Ihre Großzügigkeit und Ihr Vertrauen in unser Projekt.</p>
            <div class="mission-links">
                <a href="/geschichte" class="hero-button">Mehr über unsere Mission</a>
                <a href="/spenden" class="hero-button">Direkt spenden</a>
            </div>
        </section>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Skript für "Copy to clipboard"
        const copyToClipboardElements = document.querySelectorAll('.copy-to-clipboard');
        copyToClipboardElements.forEach(element => {
            element.addEventListener('click', function(event) {
                event.preventDefault(); // Verhindert standardmäßiges Linkverhalten, falls es ein Link ist
                const textToCopy = this.querySelector('.detail-value').innerText.trim();
                navigator.clipboard.writeText(textToCopy).then(() => {
                    const originalText = this.querySelector('.detail-value').innerText;
                    this.querySelector('.detail-value').innerText = 'Kopiert!';
                    this.classList.add('copied');
                    setTimeout(() => {
                        this.querySelector('.detail-value').innerText = originalText;
                        this.classList.remove('copied');
                    }, 1500);
                }).catch(err => {
                    console.error('Fehler beim Kopieren: ', err);
                });
            });
        });
    });
</script>