<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Früchte aus Portugal</title>

    <!-- CSS-Dateien und Icons -->
    <link rel="stylesheet" href="/.main.css">
    <link rel="icon" type="image/x-icon" href="/_favicon.ico">
    <link rel="icon" type="image/png" href="/-logo.png">
    <link rel="apple-touch-icon" href="/-apple-touch-icon.png">
</head>
<body>
    <?php
    // NEU: disclaimer_modal.php enthält jetzt nur PHP-Logik und setzt $show_disclaimer_modal.
    // Das HTML des Modals wird HIER gerendert, wenn $show_disclaimer_modal true ist.
    require_once ROOT_PATH . 'include/disclaimer_modal.php';

    if ($show_disclaimer_modal):
    ?>
    <div id="disclaimerModal" class="disclaimer-modal">
        <div class="disclaimer-modal-content">
            <h2>Wichtiger Hinweis: Testumgebung!</h2>
            <p>Dies ist eine <strong>TESTUMGEBUNG</strong> für den Online-Shop "Früchte aus Portugal".</p>
            <p>Bitte beachten Sie Folgendes:</p>
            <ul>
                <li><strong>Kein bindender Kauf:</strong> Ihre Bestellung ist <strong>nicht bindend</strong>. Es werden keine realen Produkte versandt, und es wird kein Geld abgebucht.</li>
                <li><strong>Dummy-Zahlungen:</strong> Alle Zahlungsoptionen sind nur zu Testzwecken.</li>
                <li><strong>Testdaten:</strong> Alle angezeigten Preise und Produktinformationen sind fiktiv.</li>
                <li><strong>Feedback willkommen:</strong> Melden Sie Fehler und Anregungen gerne an <a href="mailto:info@früch.de">info@früch.de</a>.</li>
            </ul>
            <p>Mit der Bestätigung dieses Hinweises verstehen Sie, dass dies keine produktive Seite ist und keine echten Transaktionen durchgeführt werden.</p>
            
            <div class="disclaimer-button-wrapper">
                <button id="disclaimer-accept-button" class="disclaimer-accept-btn" disabled>
                    Verstanden (<span id="disclaimer-countdown">5</span>)
                </button>
            </div>
            <p class="disclaimer-bottom-note">Sie müssen den Hinweis akzeptieren, um die Webseite zu nutzen.</p>
        </div>
    </div>
    <?php endif; // Ende des if($show_disclaimer_modal) Blocks ?>

    <header>
        <div class="google-header-wrapper">
            <nav class="google-search-like-nav">
                <ul class="nav-options">
                    <li><a href="/">Home</a></li>
                    <li><a href="/shop">Bestellen</a></li>
                    <li><a href="/geschichte">Geschichte</a></li>
                    <li><a href="/kontakt">Kontakt</a></li>
                </ul>
                <a href="/spenden" class="cart-button">Spenden</a>
            </nav>
        </div>
    </header>

    <div id="page-content-wrapper">

    <!-- NEU: JavaScript für das Disclaimer Modal hier einbinden -->
    <?php if ($show_disclaimer_modal ?? false): // Nur einbinden, wenn das Modal auch gerendert wird ?>
        <script src="/_disclaimer.js" defer></script>
    <?php endif; ?>