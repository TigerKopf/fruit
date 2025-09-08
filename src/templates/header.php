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
    <?php endif; ?>

    <header>
        <div class="google-header-wrapper">
            <!-- Hamburger Icon für Mobile -->
            <button class="menu-toggle" aria-label="Menü öffnen" style="display: none;">
                <span class="hamburger"></span>
            </button>
            
            <!-- Logo, das auf Mobile im Header Wrapper sichtbar ist -->
            <a href="/" class="site-logo-link">
                <img src="/-full_logo.png" alt="Früchte aus Portugal Logo" class="site-logo-image" loading="eager">
            </a>

            <!-- Der eigentliche Navigationsbereich (der ausklappt) -->
            <nav class="google-search-like-nav">
                <ul class="nav-options">
                    <li><a href="/">Home</a></li>
                    <li><a href="/shop">Bestellen</a></li>
                    <li><a href="/geschichte">Geschichte</a></li>
                    <li><a href="/kontakt">Kontakt</a></li>
                </ul>
            </nav>

            <!-- Spenden Button, der sowohl für Desktop als auch Mobile dient und bei Mobile immer angezeigt wird -->
            <a href="/spenden" class="cart-button donate-button">Spenden</a>
        </div>
    </header>

    <div id="page-content-wrapper">

    <?php if ($show_disclaimer_modal ?? false): ?>
        <script src="/_disclaimer.js" defer></script>
    <?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const menuToggle = document.querySelector('.menu-toggle');
        const googleHeaderWrapper = document.querySelector('.google-header-wrapper');
        // NEU: Das korrekte Element für die Navigation auswählen
        const googleSearchLikeNav = document.querySelector('.google-search-like-nav');

        // Prüfen, ob alle benötigten Elemente existieren
        if (menuToggle && googleSearchLikeNav && googleHeaderWrapper) {
            menuToggle.addEventListener('click', () => {
                // Die Klasse 'is-open' auf das Navigations-Container-Element anwenden
                googleSearchLikeNav.classList.toggle('is-open');
                menuToggle.classList.toggle('is-active');
                googleHeaderWrapper.classList.toggle('nav-expanded');
                menuToggle.blur();
            });

            window.addEventListener('resize', () => {
                if (window.innerWidth > 768) { // Desktop-Breakpoint
                    // Klasse entfernen, wenn zur Desktop-Ansicht gewechselt wird
                    googleSearchLikeNav.classList.remove('is-open');
                    menuToggle.classList.remove('is-active');
                    googleHeaderWrapper.classList.remove('nav-expanded');
                    document.body.style.overflow = '';
                }
            });

            document.addEventListener('keydown', (event) => {
                // Menü mit ESC schließen
                if (event.key === 'Escape' && googleSearchLikeNav.classList.contains('is-open')) {
                    googleSearchLikeNav.classList.remove('is-open');
                    menuToggle.classList.remove('is-active');
                    googleHeaderWrapper.classList.remove('nav-expanded');
                    document.body.style.overflow = '';
                    menuToggle.focus();
                }
            });
        }
    });
</script>