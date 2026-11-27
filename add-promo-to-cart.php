<?php

session_start();

require 'config/database.php';
require_once 'config/customer.php';
require_once 'config/promos.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: promo.php');
    exit;
}

if (!customerCsrfValidate($_POST['csrf_token'] ?? null)) {
    $_SESSION['cart_error'] = 'Sesi keranjang tidak valid. Silakan coba lagi.';
    header('Location: cart.php');
    exit;
}

$promoId = isset($_POST['promo_id']) ? (int) $_POST['promo_id'] : 0;
if (!$promoId || !promoSchemaReady($pdo)) {
    $_SESSION['cart_error'] = 'Promo belum dapat dipesan karena konfigurasi produk atau harga belum lengkap.';
    header('Location: cart.php');
    exit;
}

$promoStmt = $pdo->prepare("
    SELECT id, title, promo_price, active_weekdays, is_active, starts_at, ends_at
    FROM promos
    WHERE id = ?
    LIMIT 1
");
$promoStmt->execute([$promoId]);
$promo = $promoStmt->fetch();

if (!$promo || (float) $promo['promo_price'] <= 0 || !promoAvailableNow($promo)) {
    $_SESSION['cart_error'] = 'Promo tidak tersedia atau sudah tidak berlaku.';
    header('Location: cart.php');
    exit;
}

$linkedStmt = $pdo->prepare("
    SELECT pp.product_id, pp.quantity, p.name, p.stock, p.is_available
    FROM promo_products pp
    INNER JOIN products p ON p.id = pp.product_id
    WHERE pp.promo_id = ?
");
$linkedStmt->execute([$promoId]);
$linkedProducts = $linkedStmt->fetchAll();

if (!$linkedProducts) {
    $_SESSION['cart_error'] = 'Isi paket promo belum dikonfigurasi pada Manajemen Promo.';
    header('Location: cart.php');
    exit;
}

$currentQuantity = (int) ($_SESSION['promo_cart'][$promoId] ?? 0);
$nextQuantity = $currentQuantity + 1;
foreach ($linkedProducts as $linkedProduct) {
    $required = $nextQuantity * (int) $linkedProduct['quantity'];
    if ((int) $linkedProduct['is_available'] !== 1
        || $linkedProduct['stock'] === null
        || $required > (int) $linkedProduct['stock']) {
        $_SESSION['cart_error'] = 'Stok komponen promo tidak mencukupi untuk paket ' . $promo['title'] . '.';
        header('Location: cart.php');
        exit;
    }
}

if (!isset($_SESSION['promo_cart'])) {
    $_SESSION['promo_cart'] = [];
}
$_SESSION['promo_cart'][$promoId] = $nextQuantity;
header('Location: cart.php');
exit;
