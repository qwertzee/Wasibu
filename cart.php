<?php

session_start();

require_once 'config/navigation.php';
require 'config/database.php';
require_once 'config/customer.php';
require_once 'config/promos.php';


/*
|--------------------------------------------------------------------------
| Ambil cart dari session
|--------------------------------------------------------------------------
*/

$cart = $_SESSION['cart'] ?? [];
$promoCart = promoSchemaReady($pdo) ? ($_SESSION['promo_cart'] ?? []) : [];
$promoCartItems = [];

$products = [];

$total = 0;
$cartError = $_SESSION['cart_error'] ?? '';
unset($_SESSION['cart_error']);


/*
|--------------------------------------------------------------------------
| Kalau keranjang punya isi
|--------------------------------------------------------------------------
*/

if (!empty($cart)) {

    $productIds = array_keys($cart);

    $placeholders = implode(
        ',',
        array_fill(0, count($productIds), '?')
    );


    $sql = "
        SELECT
            p.*,
            c.name AS category_name

        FROM products p

        JOIN categories c
            ON c.id = p.category_id

        WHERE p.id IN ($placeholders)
    ";


    $stmt = $pdo->prepare($sql);

    $stmt->execute($productIds);

    $products = $stmt->fetchAll();

}

if (!empty($promoCart)) {
    $promoIds = array_keys($promoCart);
    $placeholders = implode(',', array_fill(0, count($promoIds), '?'));
    $promoStmt = $pdo->prepare("
        SELECT id, title, menu_text, promo_price
        FROM promos
        WHERE id IN ($placeholders)
          AND is_active = 1
    ");
    $promoStmt->execute($promoIds);
    foreach ($promoStmt->fetchAll() as $promo) {
        $promoCartItems[(int) $promo['id']] = $promo;
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

    <title>Keranjang - WASIBU</title>


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


<!-- ============================================================= -->
<!-- NAVBAR -->
<!-- ============================================================= -->

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



<!-- ============================================================= -->
<!-- CART -->
<!-- ============================================================= -->

<main class="container py-5">

    <div class="mb-4">

        <a href="daftar-menu.php" class="text-decoration-none text-secondary d-inline-flex align-items-center gap-2 mb-3">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Kembali ke Menu
        </a>

        <h2 class="fw-bold">
            Keranjang
        </h2>

        <p class="text-secondary">
            Menu yang kamu pilih.
        </p>

        <?php if ($cartError !== ''): ?>
            <div class="alert alert-warning" role="alert">
                <?= htmlspecialchars($cartError) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($products) || !empty($promoCartItems)): ?>

            <form
                action="cart-action.php"
                method="POST"
                class="mb-4"
            >
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(customerCsrfToken()) ?>">

                <input
                    type="hidden"
                    name="action"
                    value="clear"
                >

                <button
                    type="submit"
                    class="btn btn-outline-danger rounded-pill"
                >
                    Kosongkan Keranjang
                </button>

            </form>

        <?php endif; ?>

        <?php if (!empty($products) || !empty($promoCartItems)): ?>
        <div class="card border-0 shadow-sm mb-3 cart-account-card">
            <div class="card-body p-3 p-md-4">
                <?php if (!empty($_SESSION['customer_id'])): ?>
                    <strong>Masuk sebagai <?= htmlspecialchars((string) $_SESSION['customer_username']) ?></strong>
                    <div class="small text-secondary mt-1">Pesanan akan tersimpan pada akun kamu.</div>
                <?php else: ?>
                    <strong>Pilih cara pemesanan</strong>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <a href="login.php" class="btn btn-dark rounded-pill">Masuk ke akun</a>
                        <a href="register.php" class="btn btn-outline-dark rounded-pill">Daftar akun baru</a>
                        <span class="btn btn-outline-secondary rounded-pill disabled" aria-label="Checkout sebagai tamu">Lanjut sebagai tamu</span>
                    </div>
                    <div class="small text-secondary mt-2">Riwayat tamu hanya tersimpan di browser ini. Gunakan akun untuk melihat pesanan di perangkat lain.</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>


    <?php if (!empty($products) || !empty($promoCartItems)): ?>


        <div class="row">


            <div class="col-lg-8">

                <?php foreach ($promoCartItems as $promoItem): ?>
                    <?php
                    $promoId = (int) $promoItem['id'];
                    $quantity = (int) ($promoCart[$promoId] ?? 0);
                    $subtotal = (float) $promoItem['promo_price'] * $quantity;
                    $total += $subtotal;

                    $componentStmt = $pdo->prepare("
                        SELECT p.name, pp.quantity
                        FROM promo_products pp
                        INNER JOIN products p ON p.id = pp.product_id
                        WHERE pp.promo_id = ?
                        ORDER BY p.name
                    ");
                    $componentStmt->execute([$promoId]);
                    $components = $componentStmt->fetchAll();
                    ?>
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <small class="text-secondary">Paket Promo</small>
                            <h5 class="fw-bold mb-1"><?= htmlspecialchars($promoItem['title']) ?></h5>
                            <div class="small text-secondary mb-2">
                                Isi paket:
                                <?php foreach ($components as $index => $component): ?>
                                    <?= $index ? ' + ' : '' ?><?= (int) $component['quantity'] ?>× <?= htmlspecialchars($component['name']) ?>
                                <?php endforeach; ?>
                            </div>
                            <div class="fw-bold">Rp<?= number_format((float) $promoItem['promo_price'], 0, ',', '.') ?></div>
                            <div class="d-flex align-items-center gap-2 mt-3">
                                <form action="cart-action.php" method="POST" class="m-0">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(customerCsrfToken()) ?>">
                                    <input type="hidden" name="promo_id" value="<?= $promoId ?>">
                                    <input type="hidden" name="action" value="decrease_promo">
                                    <button type="submit" class="btn btn-outline-dark cart-qty-button" <?= $quantity <= 1 ? 'disabled' : '' ?>>−</button>
                                </form>
                                <span class="cart-quantity"><?= $quantity ?> paket</span>
                                <form action="cart-action.php" method="POST" class="m-0">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(customerCsrfToken()) ?>">
                                    <input type="hidden" name="promo_id" value="<?= $promoId ?>">
                                    <input type="hidden" name="action" value="increase_promo">
                                    <button type="submit" class="btn btn-dark cart-qty-button">+</button>
                                </form>
                            </div>
                            <div class="mt-2"><small class="text-secondary">Subtotal</small><div class="fw-semibold">Rp<?= number_format($subtotal, 0, ',', '.') ?></div></div>
                            <form action="cart-action.php" method="POST" class="mt-3">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(customerCsrfToken()) ?>">
                                <input type="hidden" name="promo_id" value="<?= $promoId ?>">
                                <input type="hidden" name="action" value="remove_promo">
                                <button type="submit" class="btn btn-link text-danger p-0 text-decoration-none">Batal</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>


                <?php foreach ($products as $product): ?>


                    <?php

                    $quantity = $cart[$product['id']];

                    $subtotal =
                        $product['base_price']
                        *
                        $quantity;

                    $total += $subtotal;

                    ?>


                    <div class="card border-0 shadow-sm mb-3">

                        <div class="card-body">

                            <div class="d-flex gap-3">


                                <!-- FOTO -->

                                <?php if (!empty($product['image'])): ?>

                                    <img
                                        src="assets/images/products/<?= htmlspecialchars($product['image']) ?>"
                                        alt="<?= htmlspecialchars($product['name']) ?>"
                                        class="cart-product-image"
                                    >

                                <?php else: ?>

                                    <div class="cart-product-placeholder">
                                        🍽️
                                    </div>

                                <?php endif; ?>



                                <!-- INFO -->

                                <div class="flex-grow-1">


                                    <small class="text-secondary">

                                        <?= htmlspecialchars(
                                            $product['category_name']
                                        ) ?>

                                    </small>


                                    <h5 class="fw-bold mb-1">

                                        <?= htmlspecialchars(
                                            $product['name']
                                        ) ?>

                                    </h5>


                                    <div class="fw-bold">

                                        Rp<?= number_format(
                                            $product['base_price'],
                                            0,
                                            ',',
                                            '.'
                                        ) ?>

                                    </div>


                                    <div class="d-flex align-items-center gap-2 mt-3">

    <!-- KURANG -->

    <form
        action="cart-action.php"
        method="POST"
        class="m-0"
    >
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(customerCsrfToken()) ?>">

        <input
            type="hidden"
            name="product_id"
            value="<?= $product['id'] ?>"
        >

        <input
            type="hidden"
            name="action"
            value="decrease"
        >

        <button
            type="submit"
            class="btn btn-outline-dark cart-qty-button"
            <?= $quantity <= 1 ? 'disabled' : '' ?>
        >
            −
        </button>

    </form>


    <!-- JUMLAH -->

    <span class="cart-quantity">

        <?= $quantity ?>

    </span>


    <!-- TAMBAH -->

    <form
        action="cart-action.php"
        method="POST"
        class="m-0"
    >

        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(customerCsrfToken()) ?>">

        <input
            type="hidden"
            name="product_id"
            value="<?= $product['id'] ?>"
        >

        <input
            type="hidden"
            name="action"
            value="increase"
        >

        <button
            type="submit"
            class="btn btn-dark cart-qty-button"
        >
            +
        </button>

    </form>

</div>
<div class="mt-2">

    <small class="text-secondary">
        Subtotal
    </small>

    <div class="fw-semibold">

        Rp<?= number_format(
            $subtotal,
            0,
            ',',
            '.'
        ) ?>

    </div>

</div>

                                    <form
                action="cart-action.php"
                method="POST"
                class="mt-3">

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(customerCsrfToken()) ?>">
                <input
                    type="hidden"
                    name="product_id"
                    value="<?= $product['id'] ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="remove"
                >

                <button
                    type="submit"
                    class="btn btn-link text-danger p-0 text-decoration-none"
                >
                    Batal
                </button>

</form>


                                </div>


                            </div>

                        </div>

                    </div>


                <?php endforeach; ?>


            </div>



            <!-- TOTAL -->

            <div class="col-lg-4">


                <div class="card border-0 shadow-sm">

                    <div class="card-body p-4">


                       <h5 class="fw-bold mb-4">
                Ringkasan Pesanan
            </h5>


            <div class="d-flex justify-content-between">

                <span>
                    Total
                </span>

                <strong>

                    Rp<?= number_format(
                        $total,
                        0,
                        ',',
                        '.'
                    ) ?>

                </strong>

            </div>


            <form
                action="checkout.php"
                method="POST"
            >

                <div class="mt-4">

                    <label
                        for="seller_note"
                        class="form-label fw-semibold"
                    >
                        Tulis catatan untuk penjual
                    </label>

                    <textarea
                        id="seller_note"
                        name="seller_note"
                        class="form-control"
                        rows="3"
                        placeholder="Contoh: sambalnya dipisah, jangan pakai bawang..."
                    ></textarea>

    </div>


    <button
        type="submit"
        class="btn btn-dark w-100 rounded-pill mt-4"
    >
        Checkout
    </button>

</form>

                    </div>

                </div>


            </div>


        </div>


    <?php else: ?>


        <div class="text-center py-5">


            <div class="fs-1 mb-3 text-danger" aria-hidden="true">
                <i class="bi bi-cart3"></i>
            </div>


            <h4 class="fw-bold">
                Keranjang masih kosong
            </h4>


            <p class="text-secondary">
                Pilih makanan yang kamu mau dulu.
            </p>


            <a
                href="menu.php"
                class="btn btn-dark rounded-pill px-4"
            >
                Lihat Menu
            </a>


        </div>


    <?php endif; ?>


</main>



<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>
<script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>


</body>

</html>