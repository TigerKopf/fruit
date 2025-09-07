<?php
// templates/footer.php
// Dieser Footer wird durch index.php eingebunden
?>

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
                    <!-- Annahme: Du hast diese Icons im assets-Ordner als _whatsapp.png und _instagram.png -->
                    <a href="https://www.efbornheim.de/" target="_blank" aria-label="Webseite"><img src="/_webseite.png" alt="Webseite"></a>
                    <a href="https://chat.whatsapp.com/BaBd04yeoGvDkcGZcRNkTI?mode=ac_t" target="_blank" aria-label="WhatsApp"><img src="/_WhatsApp.png" alt="WhatsApp"></a>
                    <a href="https://www.youtube.com/@efbornheim" target="_blank" aria-label="Youtube"><img src="/_youtube.png" alt="Youtube"></a>
                    <a href="https://www.instagram.com/efbornheim/" target="_blank" aria-label="Twitter"><img src="/_instagram.png" alt="Instagram"></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            Made with ❤️ in Germany
        </div>
    </div>
</footer>

<!-- Include the main JavaScript for sticky header (if it's not in another common JS file) -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
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