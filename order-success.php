<?php

session_start();

require_once 'config/navigation.php';
require 'config/database.php';
require_once 'config/customer.php';
require_once 'config/promos.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: checkout.php');
    exit;
}

if (!customerCsrfValidate($_POST['csrf_token'] ?? null)) {
    $_SESSION['checkout_error'] = 'Sesi pembayaran tidak valid. Silakan ulangi checkout.';
    header('Location: checkout.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Pastikan data checkout tersedia
|--------------------------------------------------------------------------
*/

$checkout = $_SESSION['checkout'] ?? null;

$cart = $_SESSION['cart'] ?? [];
$promoCart = promoSchemaReady($pdo) ? ($_SESSION['promo_cart'] ?? []) : [];

$postedCheckoutId = (string) ($_POST['checkout_id'] ?? '');
if (!$checkout || !hash_equals((string) ($checkout['checkout_id'] ?? ''), $postedCheckoutId)) {
    header('Location: checkout.php');
    exit;
}

if (!empty($_SESSION['last_order']['checkout_id'])
    && hash_equals((string) $_SESSION['last_order']['checkout_id'], $postedCheckoutId)) {
    header('Location: pesanan-saya.php');
    exit;
}


if (!$checkout || (empty($cart) && empty($promoCart))) {

    header('Location: menu.php');
    exit;

}


/*
|--------------------------------------------------------------------------
| Ambil data customer
|--------------------------------------------------------------------------
*/

$customerName = $checkout['buyer_name'] ?? '';

$phone = $checkout['buyer_phone'] ?? '';

$customerNote = $checkout['seller_note'] ?? null;
$paymentMethod = ($checkout['payment_method'] ?? 'qris') === 'cash' ? 'cash' : 'qris';
$paymentMethodLabel = $paymentMethod === 'cash' ? 'Tunai di Kasir' : 'QRIS';
$orderPaymentStatus = $paymentMethod === 'cash' ? 'unpaid' : 'waiting_confirmation';
$paymentRecordStatus = $paymentMethod === 'cash' ? 'pending' : 'waiting_confirmation';


/*
|--------------------------------------------------------------------------
| Ambil produk dari database
|--------------------------------------------------------------------------
*/

$products = [];
if (!empty($cart)) {
    $productIds = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = $pdo->prepare("
        SELECT id, name, base_price
        FROM products
        WHERE id IN ($placeholders)
          AND is_available = 1
    ");
    $stmt->execute($productIds);
    $products = $stmt->fetchAll();
}


if (empty($products) && empty($promoCart)) {

    header('Location: cart.php');
    exit;

}


/*
|--------------------------------------------------------------------------
| Hitung total ulang dari database
|--------------------------------------------------------------------------
*/

$total = 0;


foreach ($products as $product) {

    $quantity = $cart[$product['id']] ?? 0;

    if ($quantity < 1) {
        continue;
    }


    $subtotal =
        $product['base_price']
        *
        $quantity;


    $total += $subtotal;
}

$promoDetails = [];
foreach ($promoCart as $promoId => $quantity) {
    $promoStmt = $pdo->prepare("
        SELECT id, title, promo_price, active_weekdays, is_active, starts_at, ends_at
        FROM promos WHERE id = ? LIMIT 1
    ");
    $promoStmt->execute([(int) $promoId]);
    $promo = $promoStmt->fetch();
    if (!$promo || (int) $quantity < 1 || (float) $promo['promo_price'] <= 0 || !promoAvailableNow($promo)) {
        $_SESSION['checkout_error'] = 'Promo berubah atau tidak tersedia. Periksa kembali keranjang.';
        header('Location: checkout.php');
        exit;
    }
    $linkedStmt = $pdo->prepare("
        SELECT pp.product_id, pp.quantity, p.name, p.stock, p.is_available
        FROM promo_products pp
        INNER JOIN products p ON p.id = pp.product_id
        WHERE pp.promo_id = ?
    ");
    $linkedStmt->execute([(int) $promoId]);
    $components = $linkedStmt->fetchAll();
    if (!$components) {
        $_SESSION['checkout_error'] = 'Isi paket promo belum dikonfigurasi.';
        header('Location: checkout.php');
        exit;
    }
    foreach ($components as $component) {
        $required = (int) $quantity * (int) $component['quantity'];
        if ((int) $component['is_available'] !== 1 || $component['stock'] === null || $required > (int) $component['stock']) {
            $_SESSION['checkout_error'] = 'Stok komponen promo berubah atau tidak mencukupi.';
            header('Location: checkout.php');
            exit;
        }
    }
    $promoDetails[(int) $promoId] = [
        'promo' => $promo,
        'quantity' => (int) $quantity,
        'components' => $components
    ];
    $total += (float) $promo['promo_price'] * (int) $quantity;
}


/*
|--------------------------------------------------------------------------
| Simpan pesanan
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();
    $orderCreatedAt = (string) $pdo->query('SELECT NOW()')->fetchColumn();
    $orderCode = 'WSB-'
        . (new DateTimeImmutable($orderCreatedAt, new DateTimeZone('Asia/Jakarta')))->format('Ymd-His')
        . '-'
        . random_int(100, 999);


    /*
    |--------------------------------------------------------------------------
    | INSERT ORDERS
    |--------------------------------------------------------------------------
    */

    if (customerOrdersColumnsExist($pdo)) {
        $customerId = !empty($_SESSION['customer_id']) ? (int) $_SESSION['customer_id'] : null;
        $guestHash = $customerId === null ? customerGuestHash() : null;
        $orderStmt = $pdo->prepare("
            INSERT INTO orders (
                customer_id,
                guest_token_hash,
                order_code,
                customer_name,
                phone,
                table_id,
                subtotal,
                total,
                order_status,
                payment_status,
                customer_note,
                created_at
            )
            VALUES (?, ?, ?, ?, ?, NULL, ?, ?, 'pending', ?, ?, ?)
        ");
        $orderStmt->execute([
            $customerId,
            $guestHash,
            $orderCode,
            $customerName,
            $phone,
            $total,
            $total,
            $orderPaymentStatus,
            $customerNote !== '' ? $customerNote : null,
            $orderCreatedAt
        ]);
    } else {
        $orderStmt = $pdo->prepare("
            INSERT INTO orders (
                order_code,
                customer_name,
                phone,
                table_id,
                subtotal,
                total,
                order_status,
                payment_status,
                customer_note,
                created_at
            )
            VALUES (?, ?, ?, NULL, ?, ?, 'pending', ?, ?, ?)
        ");
        $orderStmt->execute([
            $orderCode,
            $customerName,
            $phone,
            $total,
            $total,
            $orderPaymentStatus,
            $customerNote !== '' ? $customerNote : null,
            $orderCreatedAt
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Ambil ID order
    |--------------------------------------------------------------------------
    */

    $orderId = $pdo->lastInsertId();

    $paymentStmt = $pdo->prepare("
        INSERT INTO payments (
            order_id,
            method,
            amount,
            status,
            paid_at
        )
        VALUES (?, ?, ?, ?, NULL)
    ");
    $paymentStmt->execute([
        $orderId,
        $paymentMethodLabel,
        $total,
        $paymentRecordStatus
    ]);


    /*
    |--------------------------------------------------------------------------
    | INSERT ORDER ITEMS
    |--------------------------------------------------------------------------
    */

    $itemStmt = $pdo->prepare("
        INSERT INTO order_items (
            order_id,
            product_id,
            variant_id,
            product_name_snapshot,
            variant_name_snapshot,
            quantity,
            unit_price,
            subtotal,
            notes
        )
        VALUES (
            ?,
            ?,
            NULL,
            ?,
            NULL,
            ?,
            ?,
            ?,
            NULL
        )
    ");


    foreach ($products as $product) {

        $quantity = $cart[$product['id']] ?? 0;


        if ($quantity < 1) {
            continue;
        }


        $subtotal =
            $product['base_price']
            *
            $quantity;


        $itemStmt->execute([

            $orderId,

            $product['id'],

            $product['name'],

            $quantity,

            $product['base_price'],

            $subtotal

        ]);

    }

    foreach ($promoDetails as $promoDetail) {
        $promo = $promoDetail['promo'];
        $promoQuantity = $promoDetail['quantity'];
        $promoSubtotal = (float) $promo['promo_price'] * $promoQuantity;
        $promoHasSnapshotColumns = (bool) $pdo->query("SHOW COLUMNS FROM order_items LIKE 'promo_id'")->fetch();

        if ($promoHasSnapshotColumns) {
            $promoItemStmt = $pdo->prepare("
                INSERT INTO order_items (
                    order_id, product_id, variant_id, promo_id, promo_name_snapshot,
                    product_name_snapshot, variant_name_snapshot, quantity, unit_price, subtotal, notes
                ) VALUES (?, NULL, NULL, ?, ?, ?, NULL, ?, ?, ?, ?)
            ");
            $componentNames = [];
            foreach ($promoDetail['components'] as $component) {
                $componentNames[] = (int) $component['quantity'] . '× ' . $component['name'];
            }
            $promoItemStmt->execute([
                $orderId,
                (int) $promo['id'],
                $promo['title'],
                $promo['title'],
                $promoQuantity,
                (float) $promo['promo_price'],
                $promoSubtotal,
                'Isi paket: ' . implode(' + ', $componentNames)
            ]);

            $componentStmt = $pdo->prepare("
                INSERT INTO order_items (
                    order_id, product_id, variant_id, promo_id, promo_name_snapshot,
                    product_name_snapshot, variant_name_snapshot, quantity, unit_price, subtotal, notes
                ) VALUES (?, ?, NULL, ?, ?, ?, NULL, ?, 0, 0, ?)
            ");
            foreach ($promoDetail['components'] as $component) {
                $componentQuantity = $promoQuantity * (int) $component['quantity'];
                $componentStmt->execute([
                    $orderId,
                    (int) $component['product_id'],
                    (int) $promo['id'],
                    $promo['title'],
                    $component['name'],
                    $componentQuantity,
                    'Komponen paket ' . $promo['title']
                ]);
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Selesaikan transaksi
    |--------------------------------------------------------------------------
    */

    $pdo->commit();




   /*
|--------------------------------------------------------------------------
| Simpan data untuk halaman sukses
|--------------------------------------------------------------------------
*/

$_SESSION['last_order'] = [
    'checkout_id' => $postedCheckoutId,

    'order_id' => $orderId,

    'order_code' => $orderCode,

    'customer_name' => $customerName,

    'total' => $total,

    'payment_method' => $paymentMethodLabel,

    'payment_status' => $orderPaymentStatus

];


/*
|--------------------------------------------------------------------------
| Simpan riwayat pesanan customer
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['customer_orders'])) {
    $_SESSION['customer_orders'] = [];
}

if (!in_array(
    $orderId,
    $_SESSION['customer_orders'],
    true
)) {
    $_SESSION['customer_orders'][] = $orderId;
}


/*
|--------------------------------------------------------------------------
| Kosongkan keranjang
|--------------------------------------------------------------------------
*/

$_SESSION['cart'] = [];
$_SESSION['promo_cart'] = [];

unset($_SESSION['seller_note']);

unset($_SESSION['checkout']);


} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }


    die(
        'Pesanan gagal disimpan: '
        . htmlspecialchars($e->getMessage())
    );

}


/*
|--------------------------------------------------------------------------
| Ambil data pesanan terakhir
|--------------------------------------------------------------------------
*/

$order = $_SESSION['last_order'];

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Pesanan Berhasil - WASIBU</title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">

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


        <div class="col-md-7 col-lg-5">


            <div class="card border-0 shadow-sm">

                <div class="card-body text-center p-5">


                    <div class="display-3 mb-3">
                        ✅
                    </div>


                    <h2 class="fw-bold mb-3">
                        <?= $paymentMethod === 'cash' ? 'Pesanan Berhasil Dibuat!' : 'Pesanan Berhasil' ?>
                    </h2>


                    <p class="text-secondary">
                        <?= $paymentMethod === 'cash'
                            ? 'Silakan Bayar Tunai di Kasir.'
                            : 'Pesanan kamu sudah diterima dan menunggu verifikasi pembayaran.' ?>
                    </p>


                    <div class="bg-light rounded-4 p-3 my-4">


                        <small class="text-secondary">
                            Kode Pesanan
                        </small>


                        <div class="fw-bold fs-5">

                            <?= htmlspecialchars(
                                $order['order_code']
                            ) ?>

                        </div>


                    </div>


                    <p class="mb-1">

                        Nama:

                        <strong>
                            <?= htmlspecialchars(
                                $order['customer_name']
                            ) ?>
                        </strong>

                    </p>


                    <p>

                        Total:

                        <strong>

                            Rp<?= number_format(
                                $order['total'],
                                0,
                                ',',
                                '.'
                            ) ?>

                        </strong>

                    </p>

                    <?php if ($paymentMethod === 'cash'): ?>
                        <div class="alert alert-warning text-start mt-4">
                            Tunjukkan nama pelanggan dan kode pesanan kepada kasir.
                            Status pembayaran: <strong>Menunggu Pembayaran</strong>.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning text-start mt-4">
                            Pembayaran QRIS kamu berstatus <strong>Menunggu Verifikasi</strong>.
                        </div>
                    <?php endif; ?>

                    <a
                        href="pesanan-saya.php"
                        class="btn btn-dark rounded-pill px-5 py-3 mb-2 w-100"
                    >
                        Lihat Pesanan Saya
                    </a>

                    <a
                        href="menu.php"
                        class="btn btn-outline-dark rounded-pill px-5 py-3 w-100"
                    >
                        Kembali ke Menu
                    </a>


                </div>

            </div>


        </div>


    </div>


</main>


<script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>
</body>

</html>