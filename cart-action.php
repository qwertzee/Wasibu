<?php

session_start();

require 'config/database.php';
require_once 'config/customer.php';


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


/*
|--------------------------------------------------------------------------
| Pastikan cart tersedia
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
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

}


header('Location: cart.php');
exit;