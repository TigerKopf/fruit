<?php
// include/helpers.php

/**
 * Formatiert einen Betrag in Euro mit spezifischen Regeln für Nachkommastellen.
 * Zeigt zwei Nachkommastellen nur an, wenn sie nicht .00 sind.
 *
 * @param float $amount Der zu formatierende Betrag.
 * @return string Der formatierte Betrag mit Euro-Symbol.
 */
if (!function_exists('formatEuroCurrency')) {
    function formatEuroCurrency(float $amount): string {
        // Überprüfen, ob der Betrag ganze Zahlen hat (keine Nachkommastellen oder .00)
        if (fmod($amount, 1.0) == 0) {
            return number_format($amount, 0, ',', '.') . ' €';
        } else {
            // Andernfalls mit zwei Nachkommastellen formatieren
            return number_format($amount, 2, ',', '.') . ' €';
        }
    }
}