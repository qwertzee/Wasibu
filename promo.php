<?php

session_start();
require_once 'config/navigation.php';
require 'config/database.php';

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

$promoStmt = $pdo->prepare("
    SELECT title, menu_text, description, terms, image_path, sort_order
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