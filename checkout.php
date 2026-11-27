<?php

session_start();

require_once 'config/navigation.php';
require 'config/database.php';
require_once 'config/customer.php';
require_once 'config/promos.php';


/*
|--------------------------------------------------------------------------
| Ambil keranjang
|--------------------------------------------------------------------------
*/

$cart = $_SESSION['cart'] ?? [];
$promoCart = promoSchemaReady($pdo) ? ($_SESSION['promo_cart'] ?? []) : [];
$checkoutError = $_SESSION['checkout_error'] ?? '';
unset($_SESSION['checkout_error']);


/*
|--------------------------------------------------------------------------
| Kalau cart kosong, kembali ke keranjang
|--------------------------------------------------------------------------
*/

if (empty($cart) && empty($promoCart)) {
    header('Location: cart.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Simpan catatan dari cart
|--------------------------------------------------------------------------
*/

$sellerNote = isset($_POST['seller_note'])
    ? trim($_POST['seller_note'])
    : ($_SESSION['seller_note'] ?? '');

$_SESSION['seller_note'] = $sellerNote;


/*
|--------------------------------------------------------------------------
| Ambil produk
|--------------------------------------------------------------------------
*/

$products = [];
if (!empty($cart)) {
    $productIds = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $sql = "SELECT p.* FROM products p WHERE p.id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($productIds);
    $products = $stmt->fetchAll();
}


/*
|--------------------------------------------------------------------------
| Hitung total
|--------------------------------------------------------------------------
*/

$total = 0;

foreach ($products as $product) {

    $quantity = $cart[$product['id']];

    if (
        (int) $product['is_available'] !== 1
        || $product['stock'] === null
        || $quantity > (int) $product['stock']
    ) {
        $checkoutError = 'Stok ' . $product['name'] . ' tidak mencukupi. Perbarui keranjang sebelum melanjutkan.';
    }

    $subtotal =
        $product['base_price']
        *
        $quantity;

    $total += $subtotal;
}

if (!empty($promoCart)) {
    $promoIds = array_keys($promoCart);
    $placeholders = implode(',', array_fill(0, count($promoIds), '?'));
    $promoStmt = $pdo->prepare("
        SELECT id, title, promo_price, active_weekdays, is_active, starts_at, ends_at
        FROM promos
        WHERE id IN ($placeholders)
    ");
    $promoStmt->execute($promoIds);
    foreach ($promoStmt->fetchAll() as $promo) {
        $promoId = (int) $promo['id'];
        $quantity = (int) ($promoCart[$promoId] ?? 0);
        if ($quantity < 1 || !promoAvailableNow($promo) || (float) $promo['promo_price'] <= 0) {
            $checkoutError = 'Promo ' . $promo['title'] . ' berubah atau tidak tersedia. Perbarui keranjang.';
            continue;
        }
        $total += (float) $promo['promo_price'] * $quantity;
        $linkedStmt = $pdo->prepare("
            SELECT pp.quantity, p.name, p.stock, p.is_available
            FROM promo_products pp
            INNER JOIN products p ON p.id = pp.product_id
            WHERE pp.promo_id = ?
        ");
        $linkedStmt->execute([$promoId]);
        $linkedProducts = $linkedStmt->fetchAll();
        if (!$linkedProducts) {
            $checkoutError = 'Isi paket promo belum dikonfigurasi.';
            continue;
        }
        foreach ($linkedProducts as $linkedProduct) {
            $required = $quantity * (int) $linkedProduct['quantity'];
            if ((int) $linkedProduct['is_available'] !== 1 || $linkedProduct['stock'] === null || $required > (int) $linkedProduct['stock']) {
                $checkoutError = 'Stok ' . $linkedProduct['name'] . ' tidak mencukupi untuk paket promo.';
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Checkout - WASIBU</title>


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


<!-- NAVBAR -->

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



<!-- CHECKOUT -->

<main class="container py-5">


    <div class="row justify-content-center g-4">


        <!-- DATA PEMBELI -->

        <div class="col-lg-7">


            <a
                href="cart.php"
                class="text-decoration-none text-secondary d-inline-block mb-4"
            >
                ← Kembali ke Keranjang
            </a>


            <h2 class="fw-bold mb-2">
                Checkout
            </h2>

            <p class="text-secondary mb-4">
                Lengkapi data sebelum melakukan pembayaran.
            </p>

            <?php if ($checkoutError !== ''): ?>
                <div class="alert alert-warning" role="alert">
                    <?= htmlspecialchars($checkoutError) ?>
                    <a href="cart.php" class="alert-link">Kembali ke keranjang</a>
                </div>
            <?php endif; ?>


            <div class="card border-0 shadow-sm checkout-card">

                <div class="card-body p-4">


                    <form
                        action="payment.php"
                        method="POST"
                    >
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(customerCsrfToken()) ?>">


                        <!-- NAMA -->

                        <div class="mb-4">

                            <label
                                for="buyer_name"
                                class="form-label fw-semibold"
                            >
                                Nama Pembeli
                            </label>

                            <input
                                type="text"
                                id="buyer_name"
                                name="buyer_name"
                                class="form-control form-control-lg"
                                placeholder="Masukkan nama kamu"
                                required
                            >

                        </div>



                        <!-- NOMOR HP -->

                        <div class="mb-4">

                            <label
                                for="buyer_phone"
                                class="form-label fw-semibold"
                            >
                                Nomor HP / WhatsApp
                            </label>

                            <input
                                type="tel"
                                id="buyer_phone"
                                name="buyer_phone"
                                class="form-control form-control-lg"
                                placeholder="Contoh: 081234567890"
                                required
                            >

                        </div>

                        <fieldset class="mb-4">
                            <legend class="form-label fw-semibold">Metode Pembayaran</legend>
                            <div class="d-flex flex-column gap-2">
                                <label class="form-check border rounded-3 p-3 ps-5">
                                    <input class="form-check-input" type="radio" name="payment_method" value="qris" checked>
                                    <span class="form-check-label fw-semibold">QRIS</span>
                                    <span class="d-block small text-secondary">Bayar dengan memindai QRIS seperti biasa.</span>
                                </label>
                                <label class="form-check border rounded-3 p-3 ps-5">
                                    <input class="form-check-input" type="radio" name="payment_method" value="cash">
                                    <span class="form-check-label fw-semibold">Tunai di Kasir</span>
                                    <span class="d-block small text-secondary">Bayar langsung di kasir setelah pesanan dibuat.</span>
                                </label>
                            </div>
                        </fieldset>


                        <input
                            type="hidden"
                            name="seller_note"
                            value="<?= htmlspecialchars($sellerNote) ?>"
                        >


                        <button
                            type="submit"
                            class="btn btn-dark w-100 rounded-pill py-3"
                            <?= $checkoutError !== '' ? 'disabled' : '' ?>
                        >
                            Lanjut ke Pembayaran
                        </button>


                    </form>


                </div>

            </div>

        </div>



        <!-- RINGKASAN -->

        <div class="col-lg-5">


            <div class="card border-0 shadow-sm checkout-summary">

                <div class="card-body p-4">


                    <h5 class="fw-bold mb-4">
                        Ringkasan Pesanan
                    </h5>


                    <?php foreach ($products as $product): ?>


                        <?php

                        $quantity = $cart[$product['id']];

                        $subtotal =
                            $product['base_price']
                            *
                            $quantity;

                        ?>


                        <div
                            class="d-flex justify-content-between gap-3 mb-3"
                        >

                            <div>

                                <div class="fw-semibold">

                                    <?= htmlspecialchars(
                                        $product['name']
                                    ) ?>

                                </div>

                                <small class="text-secondary">

                                    <?= $quantity ?> ×
                                    Rp<?= number_format(
                                        $product['base_price'],
                                        0,
                                        ',',
                                        '.'
                                    ) ?>

                                </small>

                            </div>


                            <div class="fw-semibold">

                                Rp<?= number_format(
                                    $subtotal,
                                    0,
                                    ',',
                                    '.'
                                ) ?>

                            </div>

                        </div>


                    <?php endforeach; ?>



                    <?php if ($sellerNote !== ''): ?>

                        <hr>

                        <div class="mb-3">

                            <small class="text-secondary">
                                Catatan untuk penjual
                            </small>

                            <div>
                                <?= nl2br(
                                    htmlspecialchars($sellerNote)
                                ) ?>
                            </div>

                        </div>

                    <?php endif; ?>


                    <hr>


                    <div
                        class="d-flex justify-content-between align-items-center"
                    >

                        <span class="fw-semibold">
                            Total
                        </span>

                        <span class="fs-4 fw-bold">

                            Rp<?= number_format(
                                $total,
                                0,
                                ',',
                                '.'
                            ) ?>

                        </span>

                    </div>


                </div>

            </div>

        </div>


    </div>


</main>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>
<script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>


</body>

</html>