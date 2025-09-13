<?php
// modules/home.php

// Hier könnten Sie bei Bedarf dynamische Daten laden, z.B. eine Auswahl von Produkten für den Teaser.
// Für diese Beispielseite werden wir hauptsächlich statische Inhalte verwenden,
// die das Design und die Animationen demonstrieren.

// Beispiel für das Abrufen von 3 aktiven Produkten für den Teaser
global $pdo;
$featured_products = [];
if (isset($pdo)) {
    try {
        // GEÄNDERT: 'stock_quantity' zur Abfrage hinzugefügt
        $stmt = $pdo->prepare("SELECT product_id, name, description, price, image_url, stock_quantity FROM products WHERE is_active = TRUE ORDER BY created_at DESC LIMIT 3");
        $stmt->execute();
        $featured_products = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Fehler beim Laden der Featured Products für die Homepage: " . $e->getMessage());
        // $featured_products bleibt leer
    }
}

// Die Funktion formatEuroCurrency() wird über include/helpers.php geladen und ist global verfügbar.
if (!function_exists('formatEuroCurrency')) {
    function formatEuroCurrency(float $amount): string {
        if (fmod($amount, 1.0) == 0) {
            return number_format($amount, 0, ',', '.') . ' €';
        } else {
            return number_format($amount, 2, ',', '.') . ' €';
        }
    }
}
?>

<div class="home-page-content">

    <!-- Hero Sektion -->
    <section class="hero-section">
        <div class="hero-content">
            <h1>Frische Früchte direkt aus Portugal</h1>
            <p>Erlebe den wahren Geschmack der Natur – saisonal, nachhaltig und mit Liebe geerntet.</p>
            <a href="/shop" class="hero-button">Jetzt bestellen</a>
            <span class="mission-highlight">100% des Gewinnes gehen in die Missionsarbeit!</span>
        </div>
    </section>

    <!-- Über uns / Philosophie Sektion -->
    <section class="home-section" id="about-us">
        <div class="home-section-content">
            <h2>Unsere Mission: Guter Geschmack, Gutes tun</h2>
            <p>Bei Früchte aus Portugal glauben wir an Authentizität und Qualität. Wir bringen dir handverlesene, sonnengereifte Früchte direkt von kleinen Bauernhöfen in Portugal. Ohne Umwege, ohne unnötige Zusätze – einfach purer Genuss.</p>
            <p>Doch wir sind mehr als nur ein ein gutes Geschäft. Unser Projekt verfolgt ein höheres Ziel: <strong>100% des Gewinnes aus jedem Verkauf fließen direkt in die Missionsarbeit.</strong> Mit jedem Bissen einer saftigen Orange oder einer süßen Kiwi unterstützt du wichtige Projekte, die Menschen in Not helfen und Hoffnung schenken. Dein Einkauf hat eine doppelte Wirkung!</p>
            <p>Jede Frucht erzählt eine Geschichte von Sonne, Leidenschaft und traditionellem Anbau, und trägt gleichzeitig dazu bei, die Welt ein kleines Stück besser zu machen. Entdecke den Unterschied, den echte Qualität und ein gutes Herz machen.</p>
        </div>
    </section>

    <!-- Vorteile / Features Sektion -->
    <section class="home-section" id="features">
        <div class="home-section-content">
            <h2>Warum Früchte aus Portugal wählen?</h2>
            <div class="features-grid">
                <div class="feature-item">
                    <img src="/-quality.jpeg" alt="Icon für Qualität">
                    <h3>Unvergleichliche Qualität</h3>
                    <p>Die Früchte werden frisch gepflügt. Somit haben sie viel mehr Zeit in der Sonne verbracht als übliche Früchte.</p>
                </div>
                <div class="feature-item">
                    <img src="/-freshness.jpeg" alt="Icon für Frische">
                    <h3>Garantierte Frische</h3>
                    <p>Direkt vom Baum zu dir – für ein Geschmackserlebnis wie im Urlaub.</p>
                </div>
                <div class="feature-item">
                    <img src="/-mission.jpeg" alt="Icon für Für die Missionsarbeit">
                    <h3>Für die Missionsarbeit</h3>
                    <p>Damit die Früchte zu dir kommen, gibt es viele ehrenamtliche Helfer.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Produkt Teaser Sektion -->
    <section class="home-section" id="products-teaser">
        <div class="home-section-content">
            <h2>Unsere beliebtesten Früchte</h2>
            <p>Entdecke eine Auswahl unserer exquisiten Produkte, die unsere Kunden lieben.</p>
            <!-- GEÄNDERT: 'product-teaser-grid' wird zu 'product-grid' für Konsistenz -->
            <div class="product-grid">
                <?php if (!empty($featured_products)): ?>
                    <?php foreach ($featured_products as $product):
                        // GEÄNDERT: Komplette Struktur aus shop.php übernommen
                        $is_sold_out = ((int)$product['stock_quantity'] <= 0);
                        $button_text = $is_sold_out ? 'Ausverkauft' : 'In den Warenkorb';
                        $is_disabled = $is_sold_out ? 'disabled' : '';
                        $input_value = $is_sold_out ? '0' : '1';
                    ?>
                        <div class="product-item">
                            <div class="product-image-container">
                                <img src="<?php echo htmlspecialchars($product['image_url'] ?: '/_placeholder.png'); ?>" 
                                     alt="Produktbild von <?php echo htmlspecialchars($product['name']); ?>" 
                                     loading="lazy" decoding="async">
                                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                            </div>
                            <p class="product-quantity-price-line">
                                <span class="product-unit"><?php echo htmlspecialchars($product['description']); ?> für</span> 
                                <span class="product-price-value"><?php echo formatEuroCurrency($product['price']); ?></span>
                            </p>
                            <div class="product-controls">
                                <input type="number" class="quantity-input" value="<?php echo $input_value; ?>" min="0" 
                                       max="<?php echo (int)$product['stock_quantity']; ?>" 
                                       data-product-id="<?php echo (int)$product['product_id']; ?>" <?php echo $is_disabled; ?>>
                                <button class="add-to-cart-btn"
                                        data-product-id="<?php echo (int)$product['product_id']; ?>"
                                        data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                        data-product-price="<?php echo htmlspecialchars($product['price']); ?>"
                                        data-product-stock="<?php echo (int)$product['stock_quantity']; ?>"
                                        data-product-image="<?php echo htmlspecialchars($product['image_url'] ?: '/_placeholder.png'); ?>"
                                        data-product-description="<?php echo htmlspecialchars($product['description']); ?>"
                                        <?php echo $is_disabled; ?>>
                                    <?php echo $button_text; ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Derzeit keine Produkte zum Anpreisen verfügbar. Besuchen Sie unseren <a href="/shop">Shop</a>!</p>
                <?php endif; ?>
            </div>
            <div style="margin-top: var(--spacing-xl);">
                <a href="/shop" class="hero-button">Alle Produkte entdecken</a>
            </div>
        </div>
    </section>

    <!-- Call to Action Sektion -->
    <section class="cta-section">
        <div class="home-section-content">
            <h2>Bereit, zu geniessen und zu helfen?</h2>
            <p>Tauche ein in die Welt der unvergleichlichen Geschmäcker und unterstütze gleichzeitig unsere Missionsarbeit. Jeder Einkauf zählt!</p>
            <a href="/shop" class="cta-button">Jetzt reinschauen & Gutes tun</a>
            <span class="mission-highlight" style="color: white; font-size: 1.1em; margin-top: var(--spacing-lg);">100% des Gewinnes gehen in die Missionsarbeit!</span>
        </div>
    </section>

</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Observer für Scroll-Animationen
        const animateOnScroll = () => {
            const sections = document.querySelectorAll('.home-section');

            const observerOptions = {
                root: null, // viewport
                rootMargin: '0px',
                threshold: 0.1 // 10% der Sektion muss sichtbar sein
            };

            const observer = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        // Optional: Animation nur einmal abspielen, kann aber auch wiederholt werden,
                        // wenn man aus dem Viewport scrollt und wieder hinein.
                        // observer.unobserve(entry.target);
                    } else {
                        // Optional: Elemente wieder verstecken, wenn sie nicht mehr sichtbar sind
                        // entry.target.classList.remove('is-visible');
                    }
                });
            }, observerOptions);

            sections.forEach(section => {
                observer.observe(section);
            });
        };

        animateOnScroll();
    });
</script>