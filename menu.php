<?php

require_once 'config/navigation.php';
require 'config/database.php';


/*
|--------------------------------------------------------------------------
| Ambil kategori yang dipilih
|--------------------------------------------------------------------------
*/

$categoryId = isset($_GET['category'])
    ? (int) $_GET['category']
    : 0;


/*
|--------------------------------------------------------------------------
| Ambil keyword pencarian
|--------------------------------------------------------------------------
*/

$search = isset($_GET['search'])
    ? trim($_GET['search'])
    : '';


/*
|--------------------------------------------------------------------------
| Ambil semua kategori
|--------------------------------------------------------------------------
*/

$categoryQuery = $pdo->query("
    SELECT *
    FROM categories
    ORDER BY sort_order ASC, name ASC
");

$categories = $categoryQuery->fetchAll();


/*
|--------------------------------------------------------------------------
| Ambil produk
|--------------------------------------------------------------------------
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


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            p.name LIKE ?
            OR p.description LIKE ?
        )
    ";

    $params[] = "%$search%";
    $params[] = "%$search%";
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

?>
<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>WASIBU - Menu</title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- CSS Kita -->

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">

</head>


<body class="customer-app menu-page">

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
<!-- HERO -->
<!-- ============================================================= -->

<section class="hero-section">

    <div class="container py-5">

        <span class="badge text-bg-dark mb-3">
            WASIBU
        </span>

        <h1 class="fw-bold display-6">

            Mau makan apa hari ini?

        </h1>


        <p class="text-secondary mb-4">

            Pilih makanan, cemilan, paket hemat,
            dan minuman favoritmu.

        </p>


        <!-- Search -->

        <form
            action="menu.php"
            method="GET"
            class="search-form"
        >

            <?php if ($categoryId > 0): ?>

                <input
                    type="hidden"
                    name="category"
                    value="<?= $categoryId ?>"
                >

            <?php endif; ?>


            <div class="input-group">

                <input
                    type="text"
                    name="search"
                    class="form-control form-control-lg"
                    placeholder="Cari menu..."
                    value="<?= htmlspecialchars($search) ?>"
                >


                <button
                    class="btn btn-dark px-4"
                    type="submit"
                >

                    Cari

                </button>

            </div>

        </form>

    </div>

</section>



<main class="container pb-5">


<!-- ============================================================= -->
<!-- CATEGORY -->
<!-- ============================================================= -->

<section class="mb-4">

    <div class="category-scroll">


        <a
            href="menu.php"
            class="btn rounded-pill
            <?= $categoryId === 0
                ? 'btn-dark'
                : 'btn-outline-dark'
            ?>"
        >

            Semua

        </a>


        <?php foreach ($categories as $category): ?>


            <a
                href="menu.php?category=<?= $category['id'] ?>"
                class="btn rounded-pill
                <?= $categoryId === (int) $category['id']
                    ? 'btn-dark'
                    : 'btn-outline-dark'
                ?>"
            >

                <?= htmlspecialchars($category['name']) ?>

            </a>


        <?php endforeach; ?>


    </div>

</section>



<!-- ============================================================= -->
<!-- JUMLAH MENU -->
<!-- ============================================================= -->

<section class="mb-3">

    <div
        class="d-flex justify-content-between align-items-center"
    >

        <h4 class="fw-bold mb-0">

            Daftar Menu

        </h4>


        <span class="text-secondary">

            <?= count($products) ?> menu

        </span>

    </div>

</section>



<!-- ============================================================= -->
<!-- PRODUCT -->
<!-- ============================================================= -->

<section>


    <?php if (count($products) > 0): ?>


        <div class="row g-4">


            <?php foreach ($products as $product): ?>


                <div class="col-12 col-sm-6 col-lg-4 col-xl-3">


                    <div class="card product-card h-100 <?= (int) $product['stock'] <= 0 ? 'is-stock-out' : '' ?>">

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
</div>
                        <!-- BODY -->

                        <div class="card-body d-flex flex-column">


                            <div class="mb-2">


                                <span class="product-category">

                                    <?= htmlspecialchars(
                                        $product['category_name']
                                    ) ?>

                                </span>


                                <?php if ($product['is_featured']): ?>

                                    <span
                                        class="badge text-bg-warning ms-1"
                                    >
                                        Favorit
                                    </span>

                                <?php endif; ?>


                            </div>



                            <h5 class="card-title fw-bold">

                                <?= htmlspecialchars(
                                    $product['name']
                                ) ?>

                            </h5>



                            <p class="product-description">

                                <?= htmlspecialchars(
                                    $product['description']
                                ) ?>

                            </p>



                            <div class="mt-auto">


                                <!-- Harga -->


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
                                                $product['min_variant_price'],
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
                                                $product['min_variant_price'],
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
                                                $product['compare_at_price'],
                                                0,
                                                ',',
                                                '.'
                                            ) ?>

                                        </small>


                                    <?php endif; ?>


                                    <div class="product-price">

                                        Rp<?= number_format(
                                            $product['base_price'],
                                            0,
                                            ',',
                                            '.'
                                        ) ?>

                                    </div>


                                <?php endif; ?>



                                <?php if ((int) $product['stock'] > 0): ?>
                                    <a
                                        href="product.php?id=<?= $product['id'] ?>"
                                        class="btn btn-dark w-100 mt-3"
                                    >Lihat Detail</a>
                                <?php else: ?>
                                    <button type="button" class="stock-disabled-action w-100 mt-3" disabled>Stok Habis</button>
                                <?php endif; ?>


                            </div>


                        </div>


                    </div>


                </div>


            <?php endforeach; ?>


        </div>


    <?php else: ?>


        <div class="empty-menu text-center py-5">

            <div class="fs-1 mb-3">
                🍽️
            </div>

            <h5 class="fw-bold">
                Menu tidak ditemukan
            </h5>

            <p class="text-secondary">
                Coba pilih kategori lain atau gunakan kata pencarian berbeda.
            </p>

            <a
                href="menu.php"
                class="btn btn-dark rounded-pill px-4"
            >
                Lihat semua menu
            </a>

        </div>


    <?php endif; ?>


</section>


</main>



<footer class="border-top bg-white">

    <div
        class="container text-center py-4 text-secondary"
    >

        WASIBU • Dahar Minum Ngopi Bareng

    </div>

</footer>

<!-- Bootstrap -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


<!-- JavaScript -->

<script src="assets/js/app.js"></script>


</body>

</html>