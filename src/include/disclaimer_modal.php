<?php
// include/disclaimer_modal.php

// Diese Datei enthält nur die PHP-Logik, die entscheidet, ob das Disclaimer-Modal angezeigt werden soll.
// Sie gibt KEIN HTML aus. Das HTML wird von templates/header.php gerendert,
// basierend auf der hier gesetzten Variable $show_disclaimer_modal.

// Prüfen, ob der Disclaimer-Cookie bereits gesetzt ist.
if (!isset($_COOKIE['disclaimer_accepted'])) {
    // Wenn der Cookie NICHT gesetzt ist, bereite das Modal zur Anzeige vor.
    // Wir setzen eine Session-Variable, damit das Modal nur einmal pro Session gerendert wird,
    // selbst wenn der User das Modal offen lässt und andere Seiten aufruft (ohne es zu akzeptieren).
    // Der "disclaimer_accepted" Cookie bleibt das finale Kriterium.
    if (!isset($_SESSION['disclaimer_shown'])) {
        $_SESSION['disclaimer_shown'] = true;
        $show_disclaimer_modal = true;
    } else {
        $show_disclaimer_modal = false;
    }
} else {
    $show_disclaimer_modal = false;
}

// Die Variable $show_disclaimer_modal ist nun im globalen Scope verfügbar
// und kann von templates/header.php abgefragt werden.
?>