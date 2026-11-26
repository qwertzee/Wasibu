<?php

session_start();

require_once 'config/navigation.php';
require 'config/database.php';

$cart = $_SESSION['cart'] ?? [];

$cartCount = array_sum($cart);

$favoriteProductNames = [
    'Paket 1 Geprek Puas Berlima',
    'Paket 2 Ayam Puas Rasa',
    'Paket 3 Ngemil Rame-Rame',
    'Chicken Katsu Lada Hitam'
];
$favoritePlaceholders = implode(',', array_fill(0, count($favoriteProductNames), '?'));
$favoriteProductsQuery = $pdo->prepare("
    SELECT
        p.id,
        p.name,
        p.slug,
        p.image,
        p.base_price,
        p.compare_at_price,
        p.stock,
        c.name AS category_name,
        COUNT(pv.id) AS variant_count,
        MIN(pv.price) AS min_variant_price,
        MAX(pv.price) AS max_variant_price
    FROM products p
    INNER JOIN categories c ON c.id = p.category_id
    LEFT JOIN product_variants pv
        ON pv.product_id = p.id
        AND pv.is_available = 1
    WHERE p.name IN ($favoritePlaceholders)
      AND p.is_available = 1
    GROUP BY p.id
    ORDER BY FIELD(p.name, $favoritePlaceholders)
");
$favoriteProductsQuery->execute(array_merge($favoriteProductNames, $favoriteProductNames));
$favoriteProducts = $favoriteProductsQuery->fetchAll();

$heroSlides = [
    [
        // Ganti gambar sementara dengan assets/images/wasibu-slide.jpg.
        'image' => 'assets/images/cafey.jpg',
        'alt' => 'Pemandangan Wasibu Cafe',
        'title' => 'WASIBU',
        'description' => 'Nikmati suasana Wasibu dan temukan menu favoritmu.',
        'target' => 'daftar-menu.php',
        'primaryButton' => 'Lihat Menu'
    ],
    [
        // Ganti gambar sementara dengan assets/images/promo-harian.jpg.
        'image' => 'assets/images/proha.jpeg',
        'alt' => 'Placeholder banner Promo Harian',
        'title' => 'PROMO HARIAN',
        'description' => 'Lihat promo harian Wasibu.',
        'target' => 'promo.php?campaign=harian',
        'primaryButton' => 'Lihat Promo'
    ],
    [
        // Ganti gambar sementara dengan assets/images/promo-paket-hemat.jpg.
        'image' => 'assets/images/pahe.jpeg',
        'alt' => 'Placeholder banner Promo Paket Hemat',
        'title' => 'PAKET HEMAT',
        'description' => 'Temukan informasi promo paket hemat Wasibu.',
        'target' => 'promo.php?campaign=paket-hemat',
        'primaryButton' => 'Lihat Promo'
    ]
];

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Wasibu - Warung Si Bungsu</title>

    <!-- Bootstrap -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">

    <!-- Font: DM Serif Display (judul) + DM Sans (isi) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Serif+Display&display=swap" rel="stylesheet">

    <!-- CSS utama -->
    <link href="assets/css/style.css" rel="stylesheet">
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
    </nav>


    <!-- HERO / SLIDER -->
    <section class="wasibu-hero">

        <div id="heroCarousel" class="carousel slide carousel-fade"
             data-bs-ride="carousel" data-bs-interval="5000">

            <div class="carousel-indicators">
                <?php foreach ($heroSlides as $index => $slide): ?>
                    <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?= $index ?>" class="<?= $index === 0 ? 'active' : '' ?>" <?= $index === 0 ? 'aria-current="true"' : '' ?> aria-label="Slide <?= $index + 1 ?>"></button>
                <?php endforeach; ?>
            </div>

            <div class="carousel-inner">
                <?php foreach ($heroSlides as $index => $slide): ?>
                    <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                        <img src="<?= htmlspecialchars($slide['image'], ENT_QUOTES, 'UTF-8') ?>" class="d-block w-100" alt="<?= htmlspecialchars($slide['alt'], ENT_QUOTES, 'UTF-8') ?>">

                        <div class="wasibu-hero-content">
                            <div class="container">
                                <div class="wasibu-hero-text">
                                    <h1><?= htmlspecialchars($slide['title'], ENT_QUOTES, 'UTF-8') ?></h1>
                                    <p><?= htmlspecialchars($slide['description'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <div class="d-flex flex-wrap gap-3">
                                        <a href="<?= htmlspecialchars($slide['target'], ENT_QUOTES, 'UTF-8') ?>" class="wasibu-btn wasibu-btn-primary"><?= htmlspecialchars($slide['primaryButton'], ENT_QUOTES, 'UTF-8') ?></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Sebelumnya</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Berikutnya</span>
            </button>

        </div>
    </section>

    <section class="customer-favorites" aria-labelledby="customer-favorites-title">
        <div class="container">
            <div class="customer-section-heading">
                <div>
                    <span class="wasibu-eyebrow">Pilihan Wasibu</span>
                    <h2 id="customer-favorites-title">Menu Favorit</h2>
                    <p>Menu pilihan untuk menemani waktu makan dan santai.</p>
                </div>
                <a href="daftar-menu.php" class="customer-section-link">Lihat semua menu <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            </div>

            <div class="row g-3 g-lg-4">
                <?php foreach ($favoriteProducts as $favoriteProduct): ?>
                    <?php
                    $favoriteImagePath = !empty($favoriteProduct['image'])
                        ? 'assets/images/products/' . $favoriteProduct['image']
                        : 'assets/images/products/' . $favoriteProduct['slug'] . '.jpg';
                    $favoriteStockOut = (int) $favoriteProduct['stock'] <= 0;
                    ?>
                    <div class="col-6 col-lg-3">
                        <article class="card favorite-product-card h-100 <?= $favoriteStockOut ? 'is-stock-out' : '' ?>">
                            <div class="favorite-product-media">
                                <?php if (file_exists($favoriteImagePath)): ?>
                                    <img
                                        src="<?= htmlspecialchars($favoriteImagePath) ?>"
                                        alt="<?= htmlspecialchars($favoriteProduct['name']) ?>"
                                        loading="lazy"
                                    >
                                <?php else: ?>
                                    <div class="favorite-product-placeholder" aria-hidden="true"><i class="bi bi-cup-hot"></i></div>
                                <?php endif; ?>
                                <?php if ($favoriteStockOut): ?>
                                    <span class="favorite-stock-badge">Stok Habis</span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body d-flex flex-column">
                                <span class="favorite-product-category"><?= htmlspecialchars($favoriteProduct['category_name']) ?></span>
                                <h3><?= htmlspecialchars($favoriteProduct['name']) ?></h3>
                                <div class="favorite-product-price">
                                    <?php if ((int) $favoriteProduct['variant_count'] > 0): ?>
                                        Rp<?= number_format((float) $favoriteProduct['min_variant_price'], 0, ',', '.') ?>
                                    <?php else: ?>
                                        Rp<?= number_format((float) $favoriteProduct['base_price'], 0, ',', '.') ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($favoriteStockOut): ?>
                                    <button type="button" class="btn customer-favorite-button mt-auto" disabled>Stok Habis</button>
                                <?php else: ?>
                                    <a href="product.php?id=<?= (int) $favoriteProduct['id'] ?>" class="btn customer-favorite-button mt-auto">Lihat Menu</a>
                                <?php endif; ?>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- WELCOME -->
    <section class="wasibu-welcome" id="tentang">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">

                    <span class="wasibu-eyebrow">Tentang Kami</span>

                    <h2>Welcome to Wasibu</h2>
                    <p>
                        Welcome to Wasibu Cafe, a cozy place to enjoy delicious
                        food and drinks in a comfortable atmosphere.
                    </p>
                </div>
            </div>
        </div>
    </section>

<!-- ============================================================= -->
<!-- AMBIANCE WASIBU -->
<!-- ============================================================= -->

<section class="wasibu-ambiance">

    <div class="container">

        <div class="text-center mb-5">

            <span class="wasibu-eyebrow">
                Suasana Wasibu
            </span>

            <h2>
                Nikmati Suasana yang Nyaman
            </h2>

            <p>
                Tempat yang nyaman untuk makan, ngobrol,
                mengerjakan tugas, atau sekadar menikmati waktu santai.
            </p>

        </div>


        <div
            id="ambianceCarousel"
            class="carousel slide wasibu-ambiance-carousel"
            data-bs-ride="false"
            data-bs-touch="true"
        >

            <div class="carousel-inner">

                <!-- ================================================= -->
                <!-- SLIDE 1 : FOTO 1 - 3 -->
                <!-- ================================================= -->

                <div class="carousel-item active">

                    <div class="row g-3">

                        <div class="col-4">
                            <div class="wasibu-ambiance-item">
                                <img
                                    src="assets/images/ambie1.jpg"
                                    alt="Suasana Wasibu Cafe"
                                    loading="lazy"
                                >
                            </div>
                        </div>

                        <div class="col-4">
                            <div class="wasibu-ambiance-item">
                                <img
                                    src="assets/images/ambie2.jpg"
                                    alt="Area Wasibu Cafe"
                                    loading="lazy"
                                >
                            </div>
                        </div>

                        <div class="col-4">
                            <div class="wasibu-ambiance-item">
                                <img
                                    src="assets/images/ambie3.jpg"
                                    alt="Suasana santai Wasibu Cafe"
                                    loading="lazy"
                                >
                            </div>
                        </div>

                    </div>

                </div>


                <!-- ================================================= -->
                <!-- SLIDE 2 : FOTO 4 - 6 -->
                <!-- ================================================= -->

                <div class="carousel-item">

                    <div class="row g-3">

                        <div class="col-4">
                            <div class="wasibu-ambiance-item">
                                <img
                                    src="assets/images/Cafe4.jpg"
                                    alt="Interior Wasibu Cafe"
                                    loading="lazy"
                                >
                            </div>
                        </div>

                        <div class="col-4">
                            <div class="wasibu-ambiance-item">
                                <img
                                    src="assets/images/Cafe5.jpg"
                                    alt="Area duduk Wasibu Cafe"
                                    loading="lazy"
                                >
                            </div>
                        </div>

                        <div class="col-4">
                            <div class="wasibu-ambiance-item">
                                <img
                                    src="assets/images/Cafe6.jpg"
                                    alt="Ambiance Wasibu Cafe"
                                    loading="lazy"
                                >
                            </div>
                        </div>

                    </div>

                </div>

            </div>


            <!-- PANAH KIRI -->
            <button
                class="carousel-control-prev wasibu-gallery-control wasibu-gallery-prev"
                type="button"
                data-bs-target="#ambianceCarousel"
                data-bs-slide="prev"
                aria-label="Foto sebelumnya"
            >
                <i class="bi bi-chevron-left"></i>
            </button>


            <!-- PANAH KANAN -->
            <button
                class="carousel-control-next wasibu-gallery-control wasibu-gallery-next"
                type="button"
                data-bs-target="#ambianceCarousel"
                data-bs-slide="next"
                aria-label="Foto berikutnya"
            >
                <i class="bi bi-chevron-right"></i>
            </button>

        </div>

    </div>

</section>

    <!-- ABOUT OUR CAFE -->
    <section class="wasibu-about" id="kontak">
        <div class="container">
            <div class="text-center mb-5">

                <span class="wasibu-eyebrow">Info Kafe</span>

                <h2>About Our Cafe</h2>
                <p>Everything you need to know about Wasibu Cafe.</p>
            </div>

            <div class="row g-4 justify-content-center">

              <div class="col-md-6 col-lg-3">

    <a
        href="https://maps.app.goo.gl/hy1aGCmZ85MKNc1B9"
        target="_blank"
        rel="noopener noreferrer"
        class="wasibu-location-link"
        aria-label="Buka lokasi Wasibu Cafe di Google Maps"
    >

        <div class="wasibu-about-item text-center">

            <span class="wasibu-about-icon">
                <i class="bi bi-geo-alt"></i>
            </span>

            <h5>Lokasi</h5>

            <p>
                Wasibu, Warung Si Bungsu
            </p>

            <span class="wasibu-location-action">
                Buka di Google Maps
                <i class="bi bi-box-arrow-up-right"></i>
            </span>

        </div>

    </a>

</div>

                <div class="col-md-6 col-lg-3">
                    <div class="wasibu-about-item text-center">
                        <span class="wasibu-about-icon">
                            <i class="bi bi-clock"></i>
                        </span>
                        <h5>Opening Hours</h5>
                        <p>Everyday<br>08:00 AM - 08:00 PM</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="wasibu-about-item text-center">
                        <span class="wasibu-about-icon">
                            <i class="bi bi-telephone"></i>
                        </span>
                        <h5>Contact</h5>
                        <p>+62 852-1189-4007</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="wasibu-about-item text-center">
                        <span class="wasibu-about-icon">
                            <i class="bi bi-instagram"></i>
                        </span>
                        <h5>Instagram</h5>
                        <p>@wasibu.cafe</p>
                    </div>
                </div>

            </div>
        </div>
    </section>

<!-- ============================================================= -->
<!-- TESTIMONI -->
<!-- ============================================================= -->

<section class="wasibu-testimonials">

    <div class="container">

        <div class="text-center mb-5">

            <span class="wasibu-eyebrow">
                Kata Mereka
            </span>

            <h2>
                Cerita dari Pengunjung Wasibu
            </h2>

            <p>
                Beberapa kesan dari pengunjung yang menikmati
                makanan dan suasana di Wasibu.
            </p>

        </div>


        <div
            id="testimonialCarousel"
            class="carousel slide wasibu-testimonial-carousel"
            data-bs-ride="false"
            data-bs-touch="true"
        >

            <div class="carousel-inner">


                <!-- SLIDE PERTAMA -->
                <div class="carousel-item active">

                    <div class="row g-4">


                        <!-- ANDINI -->
                        <div class="col-md-4">

                            <div class="wasibu-testimonial-card h-100">

                                <div class="wasibu-testimonial-stars">
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                </div>

                                <p class="wasibu-testimonial-text">
                                    “Tempatnya nyaman banget buat nongkrong.
                                    Makanannya juga enak dan pilihan menunya banyak.”
                                </p>

                                <div class="wasibu-testimonial-user">

                                    <div class="wasibu-testimonial-avatar">
                                        A
                                    </div>

                                    <div>
                                        <strong>Andini</strong>
                                        <span>Pelanggan Wasibu</span>
                                    </div>

                                </div>

                            </div>

                        </div>


                        <!-- RAKA -->
                        <div class="col-md-4">

                            <div class="wasibu-testimonial-card h-100">

                                <div class="wasibu-testimonial-stars">
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                </div>

                                <p class="wasibu-testimonial-text">
                                    “Cocok banget buat nugas sambil ngopi.
                                    Suasananya santai dan pelayanannya juga ramah.”
                                </p>

                                <div class="wasibu-testimonial-user">

                                    <div class="wasibu-testimonial-avatar">
                                        R
                                    </div>

                                    <div>
                                        <strong>Raka</strong>
                                        <span>Pelanggan Wasibu</span>
                                    </div>

                                </div>

                            </div>

                        </div>


                        <!-- NABILA -->
                        <div class="col-md-4">

                            <div class="wasibu-testimonial-card h-100">

                                <div class="wasibu-testimonial-stars">
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                </div>

                                <p class="wasibu-testimonial-text">
                                    “Pesan makanannya gampang dan nggak ribet.
                                    Bakal balik lagi buat cobain menu lainnya.”
                                </p>

                                <div class="wasibu-testimonial-user">

                                    <div class="wasibu-testimonial-avatar">
                                        N
                                    </div>

                                    <div>
                                        <strong>Nabila</strong>
                                        <span>Pelanggan Wasibu</span>
                                    </div>

                                </div>

                            </div>

                        </div>


                    </div>

                </div>


                <!-- SLIDE KEDUA -->
                <div class="carousel-item">

                    <div class="row g-4">


                        <!-- DIMAS -->
                        <div class="col-md-4">

                            <div class="wasibu-testimonial-card h-100">

                                <div class="wasibu-testimonial-stars">
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                </div>

                                <p class="wasibu-testimonial-text">
                                    “Tempatnya cozy dan cocok buat kumpul bersama teman.
                                    Pilihan makanannya juga beragam.”
                                </p>

                                <div class="wasibu-testimonial-user">

                                    <div class="wasibu-testimonial-avatar">
                                        D
                                    </div>

                                    <div>
                                        <strong>Dimas</strong>
                                        <span>Pelanggan Wasibu</span>
                                    </div>

                                </div>

                            </div>

                        </div>


                        <!-- SALSABILA -->
                        <div class="col-md-4">

                            <div class="wasibu-testimonial-card h-100">

                                <div class="wasibu-testimonial-stars">
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                </div>

                                <p class="wasibu-testimonial-text">
                                    “Minumannya enak dan suasananya nyaman.
                                    Enak juga kalau mau ngobrol lama bersama teman.”
                                </p>

                                <div class="wasibu-testimonial-user">

                                    <div class="wasibu-testimonial-avatar">
                                        S
                                    </div>

                                    <div>
                                        <strong>Salsabila</strong>
                                        <span>Pelanggan Wasibu</span>
                                    </div>

                                </div>

                            </div>

                        </div>


                        <!-- FAJAR -->
                        <div class="col-md-4">

                            <div class="wasibu-testimonial-card h-100">

                                <div class="wasibu-testimonial-stars">
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                </div>

                                <p class="wasibu-testimonial-text">
                                    “Pelayanannya cepat, makanannya enak,
                                    dan tempatnya bikin betah buat santai.”
                                </p>

                                <div class="wasibu-testimonial-user">

                                    <div class="wasibu-testimonial-avatar">
                                        F
                                    </div>

                                    <div>
                                        <strong>Fajar</strong>
                                        <span>Pelanggan Wasibu</span>
                                    </div>

                                </div>

                            </div>

                        </div>


                    </div>

                </div>

            </div>


            <!-- PREVIOUS -->
            <button
                class="carousel-control-prev wasibu-testi-control wasibu-testi-prev"
                type="button"
                data-bs-target="#testimonialCarousel"
                data-bs-slide="prev"
            >
                <i class="bi bi-chevron-left"></i>
            </button>


            <!-- NEXT -->
            <button
                class="carousel-control-next wasibu-testi-control wasibu-testi-next"
                type="button"
                data-bs-target="#testimonialCarousel"
                data-bs-slide="next"
            >
                <i class="bi bi-chevron-right"></i>
            </button>

        </div>

    </div>

</section>

    <!-- ORDER NOW -->
    <section class="wasibu-order-section">
        <div class="container text-center">

            <span class="wasibu-eyebrow">Lapar?</span>

            <h2>Ready to Order?</h2>
            <p>Explore our menu and choose your favorite food and drinks.</p>

            <a href="menu.php" class="wasibu-order-btn">
                ORDER NOW <i class="bi bi-arrow-right"></i>
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

    <!-- FOOTER -->
    <footer class="wasibu-footer">
        <div class="container text-center">
            <h5>WASIBU</h5>
            <p>Enjoy your favorite food and drinks with Wasibu.</p>

            <p class="mb-0">
                © 2026 Wasibu. All Rights Reserved.
            </p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>

</body>

</html>