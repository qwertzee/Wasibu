<?php

session_start();
require_once 'config/navigation.php';
require 'config/database.php';
require_once 'config/customer.php';
require_once 'config/promos.php';

$cartCount = array_sum($_SESSION['cart'] ?? []);

$campaignType = ($_GET['campaign'] ?? 'harian') === 'paket-hemat' ? 'paket-hemat' : 'harian';
$categoryStmt = $pdo->prepare("
    SELECT id, title, banner_path, banner_alt
    FROM promo_categories
    WHERE slug = ?
    LIMIT 1
");
$categoryStmt->execute([$campaignType]);
$campaign = $categoryStmt->fetch();

if (!$campaign) {
    http_response_code(404);
    exit('Kategori promo tidak ditemukan.');
}

$promoColumns = promoSchemaReady($pdo)
    ? "SELECT id, title, menu_text, promo_price, active_weekdays, description, terms, image_path, sort_order, is_active, starts_at, ends_at"
    : "SELECT id, title, menu_text, NULL AS promo_price, NULL AS active_weekdays, description, terms, image_path, sort_order, is_active, starts_at, ends_at";
$promoStmt = $pdo->prepare("
    $promoColumns
    FROM promos
    WHERE category_id = ?
      AND is_active = 1
      AND (starts_at IS NULL OR starts_at <= CURDATE())
      AND (ends_at IS NULL OR ends_at >= CURDATE())
    ORDER BY sort_order ASC, id ASC
");
$promoStmt->execute([$campaign['id']]);
$campaign['cards'] = $promoStmt->fetchAll();

foreach ($campaign['cards'] as &$card) {
    $card['terms'] = preg_split('/\r\n|\r|\n/', $card['terms']);
}
unset($card);

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($campaign['title'], ENT_QUOTES, 'UTF-8') ?> - Wasibu</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>" rel="stylesheet">
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

    <main class="promo-page">
        <div class="container">
            <a href="index.php" class="promo-back-link"><i class="bi bi-arrow-left" aria-hidden="true"></i> Kembali ke Beranda</a>
            <div class="promo-banner">
                <img src="<?= htmlspecialchars($campaign['banner_path'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($campaign['banner_alt'], ENT_QUOTES, 'UTF-8') ?>" fetchpriority="high">
            </div>

            <header class="promo-intro">
                <h1><?= htmlspecialchars($campaign['title'], ENT_QUOTES, 'UTF-8') ?></h1>
            </header>

            <section class="row g-3 g-lg-4" aria-label="Promo <?= htmlspecialchars($campaign['title'], ENT_QUOTES, 'UTF-8') ?>">
                <?php foreach ($campaign['cards'] as $card): ?>
                    <div class="col-12 col-md-6">
                        <article class="promo-poster h-100">
                            <img class="promo-poster-image" src="<?= htmlspecialchars($card['image_path'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($card['title'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                            <div class="promo-poster-content">
                                <h2><?= htmlspecialchars($card['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                                <?php if ($card['menu_text'] !== null && $card['menu_text'] !== ''): ?>
                                    <p class="promo-poster-menu"><?= htmlspecialchars($card['menu_text'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <?php if ($card['description'] !== null && $card['description'] !== ''): ?>
                                    <p><?= nl2br(htmlspecialchars($card['description'], ENT_QUOTES, 'UTF-8')) ?></p>
                                <?php endif; ?>
                                <div class="promo-poster-terms">
                                    <h3>Syarat &amp; Ketentuan</h3>
                                    <ul>
                                        <?php foreach ($card['terms'] as $term): ?>
                                            <li><?= htmlspecialchars($term, ENT_QUOTES, 'UTF-8') ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php
                                $promoReady = promoSchemaReady($pdo)
                                    && (float) ($card['promo_price'] ?? 0) > 0
                                    && promoAvailableNow($card);
                                ?>
                                <?php if ($promoReady): ?>
                                    <form action="add-promo-to-cart.php" method="POST" class="mt-3">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(customerCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="promo_id" value="<?= (int) $card['id'] ?>">
                                        <button type="submit" class="btn wasibu-btn wasibu-btn-primary promo-order-button">
                                            <i class="bi bi-cart3" aria-hidden="true"></i>
                                            Pesan Sekarang
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button type="button" class="btn btn-outline-secondary mt-3 promo-order-button" disabled>
                                        <i class="bi bi-cart3" aria-hidden="true"></i>
                                        Pemesanan Belum Tersedia
                                    </button>
                                    <?php if (promoSchemaReady($pdo)): ?>
                                        <div class="small text-secondary mt-2">Promo belum dikonfigurasi atau sedang tidak berlaku.</div>
                                    <?php else: ?>
                                        <div class="small text-secondary mt-2">Hubungan produk dan harga promo perlu dikonfigurasi admin.</div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
                <?php if (!$campaign['cards']): ?>
                    <div class="col-12">
                        <div class="empty-menu text-center p-5">
                            <h2 class="h4 fw-bold">Promo baru sedang disiapkan</h2>
                            <p class="mb-0 text-secondary">Nantikan penawaran menarik berikutnya dari WASIBU.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>
</body>

</html>