<?php

session_start();

require_once 'config/navigation.php';
require 'config/database.php';
require_once 'config/customer.php';


/*
|--------------------------------------------------------------------------
| Ambil daftar ID pesanan dari session customer
|--------------------------------------------------------------------------
*/

$orderIds = $_SESSION['customer_orders'] ?? [];
$orders = [];

if (customerOrdersColumnsExist($pdo) && !empty($_SESSION['customer_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC");
    $stmt->execute([(int) $_SESSION['customer_id']]);
    $orders = $stmt->fetchAll();
} elseif (customerOrdersColumnsExist($pdo)) {
    $guestHash = customerGuestHash();
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE guest_token_hash = ? ORDER BY id DESC");
    $stmt->execute([$guestHash]);
    $orders = $stmt->fetchAll();
} elseif (!empty($orderIds)) {
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id IN ($placeholders) ORDER BY id DESC");
    $stmt->execute($orderIds);
    $orders = $stmt->fetchAll();
}


/*
|--------------------------------------------------------------------------
| Label status pesanan
|--------------------------------------------------------------------------
*/

function customerOrderStatus($status)
{

    $labels = [

        'pending' =>
            'Pesanan Baru',

        'confirmed' =>
            'Pesanan Dikonfirmasi',

        'preparing' =>
            'Sedang Diproses',

        'ready' =>
            'Pesanan Siap',

        'completed' =>
            'Pesanan Selesai',

        'cancelled' =>
            'Pesanan Dibatalkan'

    ];


    return $labels[$status]
        ?? ucfirst(
            str_replace(
                '_',
                ' ',
                $status
            )
        );
}


/*
|--------------------------------------------------------------------------
| Warna status pesanan
|--------------------------------------------------------------------------
*/

function orderStatusClass($status)
{

    switch ($status) {

        case 'pending':
            return 'text-bg-secondary';

        case 'confirmed':
            return 'text-bg-info';

        case 'preparing':
            return 'text-bg-warning';

        case 'ready':
            return 'text-bg-primary';

        case 'completed':
            return 'text-bg-success';

        case 'cancelled':
            return 'text-bg-danger';

        default:
            return 'text-bg-secondary';

    }
}


/*
|--------------------------------------------------------------------------
| Label pembayaran
|--------------------------------------------------------------------------
*/

function customerPaymentStatus($status)
{

    $labels = [

        'unpaid' =>
            'Menunggu Pembayaran',

        'waiting_confirmation' =>
            'Menunggu Verifikasi',

        'paid' =>
            'Lunas',

        'failed' =>
            'Pembayaran Gagal'

    ];


    return $labels[$status]
        ?? ucfirst(
            str_replace(
                '_',
                ' ',
                $status
            )
        );
}


/*
|--------------------------------------------------------------------------
| Warna pembayaran
|--------------------------------------------------------------------------
*/

function paymentStatusClass($status)
{

    switch ($status) {

        case 'paid':
            return 'text-bg-success';

        case 'waiting_confirmation':
        case 'unpaid':
            return 'text-bg-warning';

        case 'failed':
            return 'text-bg-danger';

        default:
            return 'text-bg-secondary';

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

    <title>
        Pesanan Saya - WASIBU
    </title>


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
<!-- RIWAYAT -->
<!-- ============================================================= -->

<main class="container py-5">


    <div
        class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4"
    >

        <div>

            <h2 class="fw-bold mb-1">
                Pesanan Saya
            </h2>

            <p class="text-secondary mb-0">
                Lihat status pesanan dan pembayaran kamu.
            </p>

        </div>


        <a
            href="pesanan-saya.php"
            class="btn btn-outline-dark rounded-pill"
        >
            ↻ Refresh Status
        </a>

    </div>



    <?php if (!empty($orders)): ?>


        <div class="row g-4">


            <?php foreach ($orders as $order): ?>


                <?php

                /*
                |--------------------------------------------------------------------------
                | Ambil item pesanan
                |--------------------------------------------------------------------------
                */

                $paymentStmt = $pdo->prepare("
                    SELECT method
                    FROM payments
                    WHERE order_id = ?
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $paymentStmt->execute([$order['id']]);
                $paymentMethod = $paymentStmt->fetchColumn();

                $itemStmt = $pdo->prepare("
                    SELECT *
                    FROM order_items
                    WHERE order_id = ?
                    ORDER BY id ASC
                ");

                $itemStmt->execute([
                    $order['id']
                ]);

                $items =
                    $itemStmt->fetchAll();

                ?>


                <div class="col-12">


                    <div
                        class="card border-0 shadow-sm rounded-4"
                    >


                        <div class="card-body p-4">


                            <!-- HEADER -->

                            <div
                                class="d-flex justify-content-between align-items-start flex-wrap gap-3"
                            >


                                <div>

                                    <small
                                        class="text-secondary"
                                    >
                                        Kode Pesanan
                                    </small>


                                    <h5 class="fw-bold mb-1">

                                        <?= htmlspecialchars(
                                            $order[
                                                'order_code'
                                            ]
                                        ) ?>

                                    </h5>


                                    <small
                                        class="text-secondary"
                                    >

                                        <?= date(
                                            'd/m/Y H:i',
                                            strtotime(
                                                $order[
                                                    'created_at'
                                                ]
                                            )
                                        ) ?>

                                    </small>

                                </div>



                                <div
                                    class="d-flex flex-wrap gap-2"
                                >


                                    <span
                                        class="badge <?= paymentStatusClass(
                                            $order[
                                                'payment_status'
                                            ]
                                        ) ?>"
                                    >

                                        <?= htmlspecialchars(
                                            customerPaymentStatus(
                                                $order[
                                                    'payment_status'
                                                ]
                                            )
                                        ) ?>

                                    </span>


                                    <span
                                        class="badge <?= orderStatusClass(
                                            $order[
                                                'order_status'
                                            ]
                                        ) ?>"
                                    >

                                        <?= htmlspecialchars(
                                            customerOrderStatus(
                                                $order[
                                                    'order_status'
                                                ]
                                            )
                                        ) ?>

                                    </span>


                                </div>


                            </div>



                            <div class="d-flex justify-content-between align-items-center gap-3 mt-3">
                                <small class="text-secondary">Metode Pembayaran</small>
                                <strong><?= htmlspecialchars((string) ($paymentMethod ?: 'QRIS')) ?></strong>
                            </div>


                            <hr class="my-4">



                            <!-- ITEMS -->

                            <?php foreach ($items as $item): ?>


                                <div
                                    class="d-flex justify-content-between gap-3 mb-2"
                                >


                                    <div>

                                        <span class="fw-semibold">

                                            <?= htmlspecialchars(
                                                $item[
                                                    'product_name_snapshot'
                                                ]
                                            ) ?>

                                        </span>


                                        <span
                                            class="text-secondary"
                                        >

                                            ×
                                            <?= (int)
                                                $item[
                                                    'quantity'
                                                ]
                                            ?>

                                        </span>

                                    </div>


                                    <div>

                                        Rp<?= number_format(
                                            $item[
                                                'subtotal'
                                            ],
                                            0,
                                            ',',
                                            '.'
                                        ) ?>

                                    </div>


                                </div>


                            <?php endforeach; ?>



                            <?php if (
                                !empty(
                                    $order[
                                        'customer_note'
                                    ]
                                )
                            ): ?>


                                <div
                                    class="bg-light rounded-3 p-3 mt-3"
                                >

                                    <small
                                        class="text-secondary"
                                    >
                                        Catatan
                                    </small>


                                    <div>

                                        <?= nl2br(
                                            htmlspecialchars(
                                                $order[
                                                    'customer_note'
                                                ]
                                            )
                                        ) ?>

                                    </div>

                                </div>


                            <?php endif; ?>



                            <hr class="my-4">



                            <!-- TOTAL -->

                            <div
                                class="d-flex justify-content-between align-items-center"
                            >

                                <span class="fw-semibold">
                                    Total
                                </span>


                                <span
                                    class="fs-5 fw-bold"
                                >

                                    Rp<?= number_format(
                                        $order['total'],
                                        0,
                                        ',',
                                        '.'
                                    ) ?>

                                </span>

                            </div>



                            <!-- STATUS MESSAGE -->

                            <div class="mt-4">


                                <?php if (
                                    $order[
                                        'payment_status'
                                    ]
                                    ===
                                    'waiting_confirmation'
                                ): ?>

                                    <div
                                        class="alert alert-warning mb-0"
                                    >
                                        Pembayaran kamu sedang
                                        menunggu verifikasi dari
                                        WASIBU.
                                    </div>


                                <?php elseif (
                                    $order[
                                        'order_status'
                                    ]
                                    === 'preparing'
                                ): ?>

                                    <div
                                        class="alert alert-warning mb-0"
                                    >
                                        👨‍🍳 Pesanan kamu sedang
                                        diproses.
                                    </div>


                                <?php elseif (
                                    $order[
                                        'order_status'
                                    ]
                                    === 'ready'
                                ): ?>

                                    <div
                                        class="alert alert-primary mb-0"
                                    >
                                        🍽️ Pesanan kamu sudah siap.
                                    </div>


                                <?php elseif (
                                    $order[
                                        'order_status'
                                    ]
                                    === 'completed'
                                ): ?>

                                    <div
                                        class="alert alert-success mb-0"
                                    >
                                        ✅ Pesanan telah selesai.
                                    </div>


                                <?php endif; ?>


                            </div>


                        </div>


                    </div>


                </div>


            <?php endforeach; ?>


        </div>


    <?php else: ?>


        <div class="text-center py-5">


            <div class="display-4 mb-3">
                🧾
            </div>


            <h4 class="fw-bold">
                Belum ada pesanan
            </h4>


            <p class="text-secondary">
                Pesanan yang kamu buat akan muncul di sini.
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


<script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>
</body>

</html>