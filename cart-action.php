<?php

session_start();

require 'config/database.php';
require_once 'config/customer.php';
require_once 'config/promos.php';


/*
|--------------------------------------------------------------------------
| Pastikan request POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cart.php');
    exit;
}

if (!customerCsrfValidate($_POST['csrf_token'] ?? null)) {
    $_SESSION['cart_error'] = 'Sesi keranjang tidak valid. Silakan coba lagi.';
    header('Location: cart.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Ambil action
|--------------------------------------------------------------------------
*/

$action = $_POST['action'] ?? '';

$productId = isset($_POST['product_id'])
    ? (int) $_POST['product_id']
    : 0;
$promoId = isset($_POST['promo_id']) ? (int) $_POST['promo_id'] : 0;


/*
|--------------------------------------------------------------------------
| Pastikan cart tersedia
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($promoId > 0 && in_array($action, ['increase_promo', 'decrease_promo', 'remove_promo'], true)) {
    if (!isset($_SESSION['promo_cart'])) {
        $_SESSION['promo_cart'] = [];
    }
    $current = (int) ($_SESSION['promo_cart'][$promoId] ?? 0);

    if ($action === 'increase_promo') {
        $next = $current + 1;
        $linkedStmt = $pdo->prepare("
            SELECT pp.quantity, p.name, p.stock, p.is_available
            FROM promo_products pp
            INNER JOIN products p ON p.id = pp.product_id
            WHERE pp.promo_id = ?
        ");
        $linkedStmt->execute([$promoId]);
        foreach ($linkedStmt->fetchAll() as $linked) {
            $required = $next * (int) $linked['quantity'];
            if ((int) $linked['is_available'] !== 1 || $linked['stock'] === null || $required > (int) $linked['stock']) {
                $_SESSION['cart_error'] = 'Stok komponen promo tidak mencukupi.';
                header('Location: cart.php');
                exit;
            }
        }
        $_SESSION['promo_cart'][$promoId] = $next;
    } elseif ($action === 'decrease_promo' && $current > 1) {
        $_SESSION['promo_cart'][$promoId] = $current - 1;
    } elseif ($action === 'remove_promo') {
        unset($_SESSION['promo_cart'][$promoId]);
    }
    header('Location: cart.php');
    exit;
}


if ($productId > 0 && $action === 'increase') {
    $stockStmt = $pdo->prepare("SELECT stock, is_available FROM products WHERE id = ?");
    $stockStmt->execute([$productId]);
    $product = $stockStmt->fetch();

    if (!$product || (int) $product['is_available'] !== 1) {
        $_SESSION['cart_error'] = 'Produk tidak tersedia.';
        header('Location: cart.php');
        exit;
    }

    if ((int) ($product['stock'] ?? 0) <= (int) ($_SESSION['cart'][$productId] ?? 0)) {
        $_SESSION['cart_error'] = 'Jumlah produk di keranjang sudah mencapai stok yang tersedia.';
        header('Location: cart.php');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Tambah quantity
|--------------------------------------------------------------------------
*/

if ($action === 'increase') {

    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId]++;
    }

}


/*
|--------------------------------------------------------------------------
| Kurangi quantity
|--------------------------------------------------------------------------
*/

elseif ($action === 'decrease') {

    if (isset($_SESSION['cart'][$productId])) {

        if ($_SESSION['cart'][$productId] > 1) {

            $_SESSION['cart'][$productId]--;

        }

    }

}


/*
|--------------------------------------------------------------------------
| Batal / hapus satu produk
|--------------------------------------------------------------------------
*/

elseif ($action === 'remove') {

    if (isset($_SESSION['cart'][$productId])) {
        unset($_SESSION['cart'][$productId]);
    }

}


/*
|--------------------------------------------------------------------------
| Kosongkan seluruh keranjang
|--------------------------------------------------------------------------
*/

elseif ($action === 'clear') {

    $_SESSION['cart'] = [];
    $_SESSION['promo_cart'] = [];

}


header('Location: cart.php');
exit;