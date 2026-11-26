<?php

session_start();

require_once 'config/navigation.php';

require 'config/database.php';


/*
|--------------------------------------------------------------------------
| Helper untuk output HTML yang aman
|--------------------------------------------------------------------------
*/

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}


/*
|--------------------------------------------------------------------------
| Keranjang
|--------------------------------------------------------------------------
| Hanya dipakai untuk menampilkan badge jumlah item di navbar.
*/

$cart = $_SESSION['cart'] ?? [];

$cartCount = array_sum($cart);


/*
|--------------------------------------------------------------------------
| Ambil kategori yang dipilih
|--------------------------------------------------------------------------
| Menggunakan parameter "category" supaya konsisten dengan menu.php.
*/

$categoryId = isset($_GET['category'])
    ? (int) $_GET['category']
    : 0;


/*
|--------------------------------------------------------------------------
| Ambil semua kategori
|--------------------------------------------------------------------------
*/

$categorySql = "
    SELECT
        id,
        name
    FROM categories
    ORDER BY
        sort_order ASC,
        name ASC
";

$categoryQuery = $pdo->prepare($categorySql);

$categoryQuery->execute();

$categories = $categoryQuery->fetchAll();


/*
|--------------------------------------------------------------------------
| Ambil produk aktif / tersedia
|--------------------------------------------------------------------------
| Struktur query mengikuti menu.php.
| Produk tidak tersedia tidak ditampilkan.
| Varian yang tidak tersedia juga tidak ikut perhitungan harga.
*/

$sql = "
    SELECT
        p.*,
        c.name AS category_name,

        COUNT(pv.id) AS variant_count,

        MIN(pv.price) AS min_variant_price,

        MAX(pv.price) AS max_variant_price

    FROM products p

    JOIN categories c
        ON c.id = p.category_id

    LEFT JOIN product_variants pv
        ON pv.product_id = p.id
        AND pv.is_available = 1

    WHERE p.is_available = 1
";

$params = [];


/*
|--------------------------------------------------------------------------
| Filter kategori
|--------------------------------------------------------------------------
*/

if ($categoryId > 0) {

    $sql .= " AND p.category_id = ? ";

    $params[] = $categoryId;
}


$sql .= "
    GROUP BY p.id

    ORDER BY
        p.is_featured DESC,
        p.id ASC
";


$productQuery = $pdo->prepare($sql);

$productQuery->execute($params);

$products = $productQuery->fetchAll();


/*
|--------------------------------------------------------------------------
| Cari nama kategori aktif
|--------------------------------------------------------------------------
| Hanya untuk membantu pesan saat suatu kategori tidak mempunyai produk.
*/

$activeCategoryName = '';

if ($categoryId > 0) {

    foreach ($categories as $category) {

        if ((int) $category['id'] === $categoryId) {

            $activeCategoryName = $category['name'];

            break;
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

    <title>Daftar Menu - Wasibu Cafe</title>


    <!-- Bootstrap lokal -->
    <link
        href="assets/vendor/bootstrap/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- Bootstrap Icons lokal -->
    <link
        href="assets/vendor/bootstrap-icons/bootstrap-icons.css"
        rel="stylesheet"
    >


    <!-- Font yang sama dengan index.php -->
    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Serif+Display&display=swap"
        rel="stylesheet"
    >


    <!-- CSS utama -->
    <link
        href="assets/css/style.css"
        rel="stylesheet"
    >

</head>


<body class="customer-app wasibu-menu-page">

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
</nav>


<!-- ============================================================= -->
<!-- HEADER -->
<!-- ============================================================= -->

<header class="wasibu-menu-header">

    <div class="container">

        <div class="row align-items-center g-4">

            <div class="col-lg-8">

                <span class="wasibu-eyebrow">
                    Menu Wasibu
                </span>

                <h1 class="mb-3">
                    Daftar Menu
                </h1>

                <p class="mb-0">
                    Lihat pilihan makanan, cemilan, minuman, dan menu favorit
                    yang tersedia di Wasibu Cafe.
                </p>

            </div>


            <div class="col-lg-4 text-lg-end">

                <a
                    href="menu.php"
                    class="wasibu-btn wasibu-btn-primary"
                >
                    Pesan Sekarang
                </a>

            </div>

        </div>

    </div>

</header>


<!-- ============================================================= -->
<!-- KONTEN MENU -->
<!-- ============================================================= -->

<main class="container wasibu-menu-main">


    <!-- ========================================================= -->
    <!-- FILTER KATEGORI -->
    <!-- ========================================================= -->

    <section class="mb-4">

        <div class="category-scroll">


            <!-- Semua kategori -->

            <a
                href="daftar-menu.php"
                class="wasibu-category-pill
                <?= $categoryId === 0 ? 'active' : '' ?>"
                <?= $categoryId === 0
                    ? 'aria-current="page"'
                    : '' ?>
            >
                Semua
            </a>


            <!-- Kategori dari database -->

            <?php foreach ($categories as $category): ?>

                <?php

                $isActive =
                    $categoryId === (int) $category['id'];

                ?>

                <a
                    href="daftar-menu.php?category=<?= (int) $category['id'] ?>"
                    class="wasibu-category-pill
                    <?= $isActive ? 'active' : '' ?>"
                    <?= $isActive
                        ? 'aria-current="page"'
                        : '' ?>
                >

                    <?= e($category['name']) ?>

                </a>

            <?php endforeach; ?>


        </div>

    </section>


    <!-- ========================================================= -->
    <!-- JUMLAH MENU -->
    <!-- ========================================================= -->

    <section class="mb-4">

        <div
            class="d-flex flex-wrap justify-content-between align-items-end gap-2"
        >

            <div>

                <span class="wasibu-eyebrow">
                    Pilihan Kami
                </span>

                <h2 class="mb-0">
                    Menu Tersedia
                </h2>

            </div>


            <span class="wasibu-menu-count">

                <?= count($products) ?> menu

            </span>

        </div>

    </section>


    <!-- ========================================================= -->
    <!-- GRID PRODUK -->
    <!-- ========================================================= -->

    <section>


        <?php if (count($products) > 0): ?>


            <div class="row g-3 g-md-4">


                <?php foreach ($products as $product): ?>


                    <!--
                        HP      : 2 kolom
                        Tablet  : 3 kolom
                        Desktop : 4 kolom
                    -->

                    <div class="col-6 col-md-4 col-lg-3">

                        <article class="card product-card h-100 <?= (int) $product['stock'] <= 0 ? 'is-stock-out' : '' ?>">


                           <!-- IMAGE OTOMATIS DARI SLUG -->

<div class="product-media">
<?php

$imagePath = "assets/images/products/" . $product['slug'] . ".jpg";

?>


<?php if (file_exists($imagePath)): ?>


    <img
        src="<?= htmlspecialchars($imagePath) ?>"
        class="product-image"
        alt="<?= htmlspecialchars($product['name']) ?>"
        loading="lazy"
    >


<?php elseif (!empty($product['image'])): ?>


    <img
        src="assets/images/products/<?= htmlspecialchars($product['image']) ?>"
        class="product-image"
        alt="<?= htmlspecialchars($product['name']) ?>"
        loading="lazy"
    >


<?php else: ?>


    <div class="product-placeholder">

        <div>

            <div class="placeholder-icon">
                🍽️
            </div>

            <small>
                Foto segera tersedia
            </small>

        </div>

    </div>


<?php endif; ?>
    <?php if ((int) $product['stock'] <= 0): ?>
        <span class="visually-hidden">Stok habis</span>
    <?php endif; ?>
</div>

                            <!-- INFORMASI PRODUK -->

                            <div class="card-body d-flex flex-column">


                                <div class="mb-2">

                                    <span class="product-category">

                                        <?= e($product['category_name']) ?>

                                    </span>

                                </div>


                                <h3 class="h5 card-title fw-bold">

                                    <?= e($product['name']) ?>

                                </h3>


                                <p
                                    class="product-description wasibu-menu-description"
                                >

                                    <?= e($product['description']) ?>

                                </p>


                                <!-- HARGA -->

                                <div class="mt-auto">


                                    <?php if (
                                        (int) $product['variant_count'] > 0
                                    ): ?>


                                        <?php if (
                                            $product['min_variant_price']
                                            ==
                                            $product['max_variant_price']
                                        ): ?>


                                            <div class="product-price">

                                                Rp<?= number_format(
                                                    (float) $product['min_variant_price'],
                                                    0,
                                                    ',',
                                                    '.'
                                                ) ?>

                                            </div>


                                        <?php else: ?>


                                            <small class="text-secondary">
                                                Mulai dari
                                            </small>


                                            <div class="product-price">

                                                Rp<?= number_format(
                                                    (float) $product['min_variant_price'],
                                                    0,
                                                    ',',
                                                    '.'
                                                ) ?>

                                            </div>


                                        <?php endif; ?>


                                    <?php else: ?>


                                        <?php if (
                                            !empty($product['compare_at_price'])
                                        ): ?>


                                            <small
                                                class="text-decoration-line-through text-secondary"
                                            >

                                                Rp<?= number_format(
                                                    (float) $product['compare_at_price'],
                                                    0,
                                                    ',',
                                                    '.'
                                                ) ?>

                                            </small>


                                        <?php endif; ?>


                                        <div class="product-price">

                                            Rp<?= number_format(
                                                (float) $product['base_price'],
                                                0,
                                                ',',
                                                '.'
                                            ) ?>

                                        </div>


                                    <?php endif; ?>


                                </div>

                            </div>

                        </article>

                    </div>


                <?php endforeach; ?>


            </div>


        <?php else: ?>


            <!-- PESAN JIKA KATEGORI KOSONG -->

            <div class="wasibu-menu-empty text-center">


                <div
                    class="fs-1 mb-3"
                    aria-hidden="true"
                >
                    🍽️
                </div>


                <h3 class="h4">
                    Belum ada menu
                </h3>


                <p class="mb-4">

                    <?php if ($activeCategoryName !== ''): ?>

                        Saat ini belum ada menu tersedia di kategori
                        <strong><?= e($activeCategoryName) ?></strong>.

                    <?php else: ?>

                        Saat ini belum ada menu yang tersedia.

                    <?php endif; ?>

                </p>


                <a
                    href="daftar-menu.php"
                    class="wasibu-btn wasibu-btn-primary"
                >
                    Lihat Semua Menu
                </a>


            </div>


        <?php endif; ?>


    </section>


</main>


<!-- ============================================================= -->
<!-- AJAKAN PESAN -->
<!-- ============================================================= -->

<section class="wasibu-order-section">

    <div class="container text-center">


        <span class="wasibu-eyebrow">
            Sudah memilih?
        </span>


        <h2>
            Sudah tahu mau pesan apa?
        </h2>


        <p>
            Masuk ke halaman pemesanan untuk memilih jumlah dan
            menambahkan menu favoritmu ke keranjang.
        </p>


       <a
    href="menu.php"
    class="wasibu-btn wasibu-btn-primary">
    Pesan Sekarang
    <i class="bi bi-arrow-right ms-2"></i>
        </a>


    </div>

</section>

<!-- ============================================================= -->
<!-- LOKASI WASIBU -->
<!-- ============================================================= -->

<section class="wasibu-location-section">

    <div class="container">

        <div class="row align-items-stretch g-4">

            <!-- INFORMASI LOKASI -->
            <div class="col-lg-5">

                <div class="wasibu-location-info h-100">

                    <span class="wasibu-eyebrow">
                        Kunjungi Kami
                    </span>

                    <h2>
                        Temukan Wasibu
                    </h2>

                    <p class="wasibu-location-intro">
                        Datang dan nikmati suasana Wasibu bersama
                        teman, keluarga, atau orang tersayang.
                    </p>


                    <!-- Alamat -->
                    <div class="wasibu-location-detail">

                        <span class="wasibu-location-icon">
                            <i class="bi bi-geo-alt"></i>
                        </span>

                        <div>
                            <h5>Alamat</h5>

                            <p>
                                Gg. Remaja 2 Blok Remaja 2 No.16,
                                RT.6/RW.3, Gedong, Kec. Ps. Rebo,
                                Kota Jakarta Timur, DKI Jakarta 13760
                            </p>
                        </div>

                    </div>


                    <!-- Jam buka -->
                    <div class="wasibu-location-detail">

                        <span class="wasibu-location-icon">
                            <i class="bi bi-clock"></i>
                        </span>

                        <div>
                            <h5>Jam Buka</h5>

                            <p>
                                Senin – Jumat: 08.00 – 20.00 WIB
                                <br>
                                Sabtu: 08.00 – 20.00 WIB
                            </p>
                        </div>

                    </div>


                    <!-- Telepon -->
                    <div class="wasibu-location-detail">

                        <span class="wasibu-location-icon">
                            <i class="bi bi-telephone"></i>
                        </span>

                        <div>
                            <h5>Telepon</h5>

                            <p>
                                +62 852-1189-4007
                            </p>
                        </div>

                    </div>


                    <!-- Tombol Google Maps -->
                    <a
                        href="https://maps.app.goo.gl/hy1aGCmZ85MKNc1B9"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="wasibu-btn wasibu-btn-primary wasibu-location-button"
                    >
                        <i class="bi bi-geo-alt-fill me-2"></i>
                        Buka di Google Maps
                    </a>

                </div>

            </div>


            <!-- GOOGLE MAPS -->
            <div class="col-lg-7">

                <div class="wasibu-map-wrapper">

                    <iframe
                        src="https://www.google.com/maps?q=Gg.%20Remaja%202%20Blok%20Remaja%202%20No.16%2C%20RT.6%2FRW.3%2C%20Gedong%2C%20Pasar%20Rebo%2C%20Jakarta%20Timur%2013760&output=embed"
                        title="Lokasi Wasibu Cafe di Google Maps"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        allowfullscreen
                    ></iframe>

                </div>

            </div>

        </div>

    </div>

</section>


<!-- ============================================================= -->
<!-- FOOTER -->
<!-- ============================================================= -->

<footer class="wasibu-footer">

    <div class="container text-center">

        <h5>
            WASIBU
        </h5>

        <p>
            Enjoy your favorite food and drinks with Wasibu.
        </p>

        <p class="mb-0">
            © 2026 Wasibu. All Rights Reserved.
        </p>

    </div>

</footer>


<!-- Bootstrap JS lokal -->

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>


</body>

</html>