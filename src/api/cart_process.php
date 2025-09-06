<?php
// api/cart_process.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Benötigte Konfiguration und Funktionen laden
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'include/db.php'; // Stellt getDbConnection() und $pdo bereit

$response = ['success' => false, 'message' => ''];

try {
    $pdo = getDbConnection(); // Sicherstellen, dass die Verbindung besteht

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

        if ($productId <= 0) {
            throw new Exception("Ungültige Produkt-ID.");
        }

        // Produktinformationen aus der Datenbank abrufen, einschließlich image_url und description
        $stmt = $pdo->prepare("SELECT product_id, name, description, price, stock_quantity, image_url FROM products WHERE product_id = :product_id AND is_active = TRUE");
        $stmt->execute([':product_id' => $productId]);
        $product = $stmt->fetch();

        if (!$product) {
            throw new Exception("Produkt nicht gefunden oder nicht aktiv.");
        }

        $availableStock = $product['stock_quantity'];

        switch ($action) {
            case 'add':
                // Prüfen, ob das Produkt bereits im Warenkorb ist
                $currentCartQuantity = isset($_SESSION['cart'][$productId]) ? $_SESSION['cart'][$productId]['quantity'] : 0;
                $newQuantity = $currentCartQuantity + $quantity;

                if ($newQuantity > $availableStock) {
                    $response['message'] = "Nicht genügend Lagerbestand. Maximal verfügbar: " . $availableStock;
                    echo json_encode($response);
                    exit();
                }

                $_SESSION['cart'][$productId] = [
                    'id' => $productId,
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'quantity' => $newQuantity,
                    'stock' => $availableStock, // Lagerbestand für clientseitige Validierung
                    'image_url' => $product['image_url'], // Bild-URL im Warenkorb speichern
                    'description' => $product['description'] // Beschreibung im Warenkorb speichern (für Alt-Text)
                ];
                $response['success'] = true;
                $response['message'] = "Produkt erfolgreich hinzugefügt.";
                break;

            case 'update':
                if (!isset($_SESSION['cart'][$productId])) {
                    throw new Exception("Produkt nicht im Warenkorb.");
                }

                if ($quantity <= 0) {
                    // Wenn Menge 0 oder weniger ist, entfernen
                    unset($_SESSION['cart'][$productId]);
                    $response['success'] = true;
                    $response['message'] = "Produkt aus dem Warenkorb entfernt.";
                } else {
                    if ($quantity > $availableStock) {
                        $response['message'] = "Nicht genügend Lagerbestand. Maximal verfügbar: " . $availableStock;
                        echo json_encode($response);
                        exit();
                    }
                    $_SESSION['cart'][$productId]['quantity'] = $quantity;
                    $response['success'] = true;
                    $response['message'] = "Menge aktualisiert.";
                }
                break;

            case 'remove':
                if (!isset($_SESSION['cart'][$productId])) {
                    throw new Exception("Produkt nicht im Warenkorb.");
                }
                unset($_SESSION['cart'][$productId]);
                $response['success'] = true;
                $response['message'] = "Produkt aus dem Warenkorb entfernt.";
                break;

            default:
                throw new Exception("Unbekannte Aktion.");
        }
    } else {
        throw new Exception("Ungültige Anfrage.");
    }

} catch (Exception $e) {
    error_log("Warenkorb-Fehler: " . $e->getMessage());
    $response['message'] = $e->getMessage();
}

$response['cart'] = $_SESSION['cart'];
echo json_encode($response);