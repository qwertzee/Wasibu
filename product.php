<?php

require_once 'config/navigation.php';
require 'config/database.php';
require_once 'config/customer.php';


/*
|--------------------------------------------------------------------------
| Ambil ID produk dari URL
|--------------------------------------------------------------------------
*/

$productId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;


/*
|--------------------------------------------------------------------------
| Ambil detail produk
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        p.*,
        c.name AS category_name
    FROM products p
    JOIN categories c
        ON c.id = p.category_id
    WHERE p.id = ?
      AND p.is_available = 1
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$productId]);

$product = $stmt->fetch();


/*
|--------------------------------------------------------------------------
| Kalau produk tidak ditemukan
|--------------------------------------------------------------------------
*/

if (!$product) {
    http_response_code(404);
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

    <title>
        <?= $product
            ? htmlspecialchars($product['name']) . ' - WASIBU'
            : 'Menu Tidak Ditemukan - WASIBU'
        ?>
    </title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- CSS -->

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
<!-- PRODUCT DETAIL -->
<!-- ============================================================= -->

<main class="container py-5">


<?php if ($product): ?>


    <div class="row justify-content-center">


        <div class="col-12 col-xl-10">


            <a
                href="menu.php"
                class="text-decoration-none text-secondary d-inline-block mb-4"
            >
                ← Kembali ke Menu
            </a>


            <div class="card border-0 product-detail-card">


                <?php 
$productImage = !empty($product['image'])
    ? $product['image']
    : $product['slug'] . '.jpg';
?>

<div class="product-detail-media <?= (int) $product['stock'] <= 0 ? 'is-stock-out' : '' ?>">
    <img
        src="assets/images/products/<?= htmlspecialchars($productImage) ?>"
        class="product-detail-image"
        alt="<?= htmlspecialchars($product['name']) ?>"
    >
</div>

                <!-- INFORMASI -->

                <div class="card-body px-0 pt-4">


                    <!-- KATEGORI -->

                    <span class="product-category">

                        <?= htmlspecialchars(
                            $product['category_name']
                        ) ?>

                    </span>



                    <!-- NAMA -->

                    <h2 class="fw-bold mt-2 mb-3">

                        <?= htmlspecialchars(
                            $product['name']
                        ) ?>

                    </h2>



                    <!-- DESKRIPSI -->

                    <p class="text-secondary">

                        <?= nl2br(
                            htmlspecialchars(
                                $product['description']
                            )
                        ) ?>

                    </p>


                    <!-- HARGA -->

                    <div class="product-price fs-4 mt-3">

                        Rp<?= number_format(
                            $product['base_price'],
                            0,
                            ',',
                            '.'
                        ) ?>

                    </div>
                    
                   <?php if ($product['stock'] > 0): ?>

    <form
        action="add-to-cart.php"
        method="POST"
        class="mt-4"
    >
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(customerCsrfToken()) ?>">

        <input
            type="hidden"
            name="product_id"
            value="<?= $product['id'] ?>"
        >

        <button
            type="submit"
            class="btn btn-dark w-100 py-3"
        >
            <i class="bi bi-cart-plus me-2" aria-hidden="true"></i>
            Tambah ke Keranjang
        </button>

    </form>


<?php else: ?>


    <button
        type="button"
        class="stock-disabled-action w-100 mt-4"
        disabled
    >
        Stok Habis
    </button>


<?php endif; ?>

                </div>

            </div>

        </div>

    </div>


<?php else: ?>


    <div class="text-center py-5">

        <div class="fs-1 mb-3">
            🍽️
        </div>

        <h4 class="fw-bold">
            Menu tidak ditemukan
        </h4>

        <p class="text-secondary">
            Menu yang kamu cari tidak tersedia.
        </p>

        <a
            href="menu.php"
            class="btn btn-dark rounded-pill px-4"
        >
            Kembali ke Menu
        </a>

    </div>


<?php endif; ?>


</main>



<!-- Bootstrap -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>
<script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>


</body>

</html>