<?php
session_start();
require 'config/database.php';
require_once 'config/customer.php';
/*
|--------------------------------------------------------------------------
| Hanya menerima POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: menu.php');
    exit;
}

if (!customerCsrfValidate($_POST['csrf_token'] ?? null)) {
    $_SESSION['cart_error'] = 'Sesi keranjang tidak valid. Silakan coba lagi.';
    header('Location: cart.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Ambil ID produk
|--------------------------------------------------------------------------
*/

$productId = isset($_POST['product_id'])
    ? (int) $_POST['product_id']
    : 0;
/*
|--------------------------------------------------------------------------
| Cek produk
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(" 
    SELECT id, stock
    FROM products
    WHERE id = ?
      AND is_available = 1
    LIMIT 1
");

$stmt->execute([$productId]);

$product = $stmt->fetch();


if (!$product) {
    header('Location: menu.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Buat cart kalau belum ada
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}


$currentQuantity = (int) ($_SESSION['cart'][$productId] ?? 0);
$availableStock = $product['stock'] === null
    ? 0
    : (int) $product['stock'];

if ($currentQuantity >= $availableStock) {
    $_SESSION['cart_error'] = 'Stok produk tidak mencukupi untuk jumlah yang diminta.';
    header('Location: cart.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Tambah produk ke keranjang
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['cart'][$productId])) {

    $_SESSION['cart'][$productId]++;

} else {

    $_SESSION['cart'][$productId] = 1;

}


/*
|--------------------------------------------------------------------------
| Pindah ke halaman keranjang
|--------------------------------------------------------------------------
*/

header('Location: cart.php');
exit;