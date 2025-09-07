// src/assets/js/disclaimer.js

document.addEventListener('DOMContentLoaded', () => {
    const disclaimerModal = document.getElementById('disclaimerModal');
    const disclaimerAcceptButton = document.getElementById('disclaimer-accept-button');
    const disclaimerCountdownSpan = document.getElementById('disclaimer-countdown');
    
    // Wenn das Modal-Element nicht existiert, bedeutet das, dass der Cookie bereits gesetzt ist
    // und das PHP das Modal nicht gerendert hat. In diesem Fall ist kein JS nötig.
    if (!disclaimerModal) {
        return;
    }

    let countdown = 5; // Sekunden für den Countdown

    // Countdown-Logik
    const countdownInterval = setInterval(() => {
        countdown--;
        disclaimerCountdownSpan.textContent = countdown;

        if (countdown <= 0) {
            clearInterval(countdownInterval);
            disclaimerAcceptButton.textContent = 'Verstanden und akzeptiert';
            disclaimerAcceptButton.disabled = false; // Button aktivieren
            // Der Cursor wird durch CSS :not([disabled]) gesteuert
        }
    }, 1000); // Alle 1 Sekunde aktualisieren

    // Event Listener für den Button-Klick
    disclaimerAcceptButton.addEventListener('click', () => {
        if (!disclaimerAcceptButton.disabled) {
            // Cookie setzen (Name, Wert, Dauer in Tagen)
            const cookieName = "disclaimer_accepted";
            const cookieValue = "true";
            const cookieDays = 3;
            
            const d = new Date();
            d.setTime(d.getTime() + (cookieDays * 24 * 60 * 60 * 1000)); // 3 Tage
            const expires = "expires=" + d.toUTCString();
            // path=/ ist wichtig, damit der Cookie für die gesamte Domain gültig ist
            document.cookie = cookieName + "=" + cookieValue + ";" + expires + ";path=/";

            // Modal ausblenden
            disclaimerModal.style.display = 'none';
            // Body-Scroll wiederherstellen
            document.body.style.overflow = '';
        }
    });

    // Beim Laden der Seite sicherstellen, dass der Body-Scroll deaktiviert ist, solange das Modal aktiv ist
    document.body.style.overflow = 'hidden';
});