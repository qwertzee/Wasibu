<?php

session_start();

require_once 'config/navigation.php';
require 'config/database.php';
require_once 'config/customer.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !customerCsrfValidate($_POST['csrf_token'] ?? null)) {
    $_SESSION['checkout_error'] = 'Sesi checkout tidak valid. Silakan coba lagi.';
    header('Location: checkout.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Harus melalui checkout
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cart.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Ambil data pembeli
|--------------------------------------------------------------------------
*/

$buyerName = trim($_POST['buyer_name'] ?? '');

$buyerPhone = trim($_POST['buyer_phone'] ?? '');

$sellerNote = trim($_POST['seller_note'] ?? '');
$paymentMethod = $_POST['payment_method'] ?? 'qris';


if (
    $buyerName === ''
    || $buyerPhone === ''
    || !in_array($paymentMethod, ['qris', 'cash'], true)
) {
    header('Location: checkout.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Ambil cart
|--------------------------------------------------------------------------
*/

$cart = $_SESSION['cart'] ?? [];


if (empty($cart)) {
    header('Location: cart.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Simpan data checkout di session
|--------------------------------------------------------------------------
*/

$_SESSION['checkout'] = [

    'buyer_name' => $buyerName,

    'buyer_phone' => $buyerPhone,

    'seller_note' => $sellerNote,

    'payment_method' => $paymentMethod

];


/*
|--------------------------------------------------------------------------
| Ambil produk
|--------------------------------------------------------------------------
*/

$productIds = array_keys($cart);

$placeholders = implode(
    ',',
    array_fill(0, count($productIds), '?')
);


$stmt = $pdo->prepare("
    SELECT *
    FROM products
    WHERE id IN ($placeholders)
");

$stmt->execute($productIds);

$products = $stmt->fetchAll();

$productById = [];
foreach ($products as $product) {
    $productById[(int) $product['id']] = $product;
}

foreach ($cart as $productId => $quantity) {
    $product = $productById[(int) $productId] ?? null;

    if (
        !$product
        || (int) $product['is_available'] !== 1
        || $product['stock'] === null
        || (int) $quantity < 1
        || (int) $quantity > (int) $product['stock']
    ) {
        $_SESSION['checkout_error'] = 'Stok produk berubah atau tidak mencukupi. Periksa kembali keranjang.';
        header('Location: checkout.php');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Hitung total
|--------------------------------------------------------------------------
*/

$total = 0;

foreach ($products as $product) {

    $quantity = $cart[$product['id']];

    $total +=
        $product['base_price']
        *
        $quantity;
}


$_SESSION['checkout']['total'] = $total;
$_SESSION['checkout']['checkout_id'] = bin2hex(random_bytes(16));

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Pembayaran - WASIBU</title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <link
        rel="stylesheet"
        href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>"
    >

</head>


<body class="customer-app">


<nav class="navbar customer-navbar" aria-label="Navigasi customer WASIBU">
    <div class="container customer-navbar-inner">
        <?= wasibuLogoLink('customer-navbar-brand') ?>
        <div class="customer-nav-track">
            <nav class="customer-nav-links" aria-label="Menu utama">
                <a href="index.php" data-customer-nav="home">Beranda</a>
                <a href="daftar-menu.php" data-customer-nav="menu">Menu</a>
                <a href="index.php#tentang" data-customer-nav="about">Tentang Kami</a>
                <a href="index.php#kontak" data-customer-nav="contact">Kontak</a>
            </nav>
            <nav class="customer-nav-actions" aria-label="Akun dan keranjang">
                <?= wasibuCustomerNavActions() ?>
            </nav>
        </div>
    </div>
    <?= wasibuCustomerMobileNavbar() ?>
</nav>



<main class="container py-5">


    <div class="row justify-content-center">


        <div class="col-12 col-md-7 col-lg-5">


            <div class="card border-0 shadow-sm payment-card">

                <div class="card-body p-4 p-md-5 text-center">


                    <h2 class="fw-bold mb-2">
                        <?= $paymentMethod === 'cash' ? 'Tunai di Kasir' : 'Pembayaran QRIS' ?>
                    </h2>

                    <p class="text-secondary mb-4">
                        <?= $paymentMethod === 'cash'
                            ? 'Pesanan akan dibuat dan pembayaran dilakukan langsung di kasir.'
                            : 'Scan QRIS untuk melakukan pembayaran.' ?>
                    </p>



                    <small class="text-secondary">
                        Total Pembayaran
                    </small>


                    <div class="fs-2 fw-bold mb-4">

                        Rp<?= number_format(
                            $total,
                            0,
                            ',',
                            '.'
                        ) ?>

                    </div>



                    <?php if ($paymentMethod === 'qris'): ?>
                        <?php if (file_exists('assets/images/qris.png')): ?>
                            <img
                                src="assets/images/qris.png"
                                alt="QRIS WASIBU"
                                class="qris-image"
                            >
                        <?php else: ?>
                            <div class="qris-placeholder">
                                <div>
                                    <div class="fs-1">📱</div>
                                    <strong>QRIS</strong>
                                    <div class="small text-secondary mt-2">Tambahkan gambar QRIS</div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-warning text-start">
                            Bayar tunai kepada kasir setelah pesanan berhasil dibuat.
                        </div>
                    <?php endif; ?>



                    <p class="text-secondary small mt-4">

                        Nama:
                        <strong>
                            <?= htmlspecialchars($buyerName) ?>
                        </strong>

                        <br>

                        Nomor HP:
                        <strong>
                            <?= htmlspecialchars($buyerPhone) ?>
                        </strong>

                    </p>



                    <form
                        action="order-success.php"
                        method="POST"
                    >
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(customerCsrfToken()) ?>">
                        <input type="hidden" name="checkout_id" value="<?= htmlspecialchars($_SESSION['checkout']['checkout_id']) ?>">

                        <button
                            type="submit"
                            class="btn btn-dark w-100 rounded-pill py-3 mt-3"
                        >
                            <?= $paymentMethod === 'cash' ? 'Buat Pesanan Tunai' : 'Saya Sudah Bayar' ?>
                        </button>

                    </form>


                </div>

            </div>

        </div>


    </div>


</main>


<script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>
</body>

</html>