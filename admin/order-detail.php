<?php

session_start();

require_once __DIR__ . '/auth.php';
require_once '../config/navigation.php';
require '../config/database.php';


/*
|--------------------------------------------------------------------------
| Ambil ID pesanan
|--------------------------------------------------------------------------
*/

$orderId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

$adminFeedback = $_SESSION['admin_feedback'] ?? null;
unset($_SESSION['admin_feedback']);


if ($orderId <= 0) {

    header('Location: dashboard.php');
    exit;

}


/*
|--------------------------------------------------------------------------
| Ambil data order
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        orders.*,
        COALESCE(
            (SELECT payments.method FROM payments WHERE payments.order_id = orders.id ORDER BY payments.id DESC LIMIT 1),
            'QRIS'
        ) AS payment_method
    FROM orders
    WHERE orders.id = ?
    LIMIT 1
");

$stmt->execute([$orderId]);

$order = $stmt->fetch();


if (!$order) {

    header('Location: dashboard.php');
    exit;

}


/*
|--------------------------------------------------------------------------
| Ambil item pesanan
|--------------------------------------------------------------------------
*/

$itemStmt = $pdo->prepare("
    SELECT *
    FROM order_items
    WHERE order_id = ?
    ORDER BY id ASC
");

$itemStmt->execute([$orderId]);

$orderItems = $itemStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Ambil pilihan status langsung dari ENUM database
|--------------------------------------------------------------------------
*/

$statusQuery = $pdo->query("
    SHOW COLUMNS
    FROM orders
    LIKE 'order_status'
");

$statusColumn = $statusQuery->fetch();

$orderStatuses = [];


if ($statusColumn) {

    preg_match_all(
        "/'([^']+)'/",
        $statusColumn['Type'],
        $matches
    );

    $orderStatuses = $matches[1] ?? [];

}


/*
|--------------------------------------------------------------------------
| Label status
|--------------------------------------------------------------------------
*/

function orderStatusLabel($status)
{

    $labels = [

        'pending' => 'Baru',
        'confirmed' => 'Dikonfirmasi',
        'preparing' => 'Diproses',
        'ready' => 'Siap',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan'

    ];

    return $labels[$status]
        ?? ucfirst(
            str_replace('_', ' ', $status)
        );

}


function paymentStatusLabel($status)
{

    $labels = [

        'unpaid' => 'Menunggu Pembayaran',

        'waiting_confirmation'
            => 'Menunggu Verifikasi',

        'paid' => 'Lunas',

        'failed' => 'Gagal'

    ];

    return $labels[$status]
        ?? ucfirst(
            str_replace('_', ' ', $status)
        );

}


function paymentStatusClass($status)
{

    switch ($status) {

        case 'paid':
            return 'text-bg-success';

        case 'waiting_confirmation':
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
        Detail Pesanan - WASIBU
    </title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >


    <style>

        body {
            background: #f7f7f7;
        }

        .detail-card {
            border: 0;
            border-radius: 20px;
        }

        .order-item {
            padding: 16px 0;
            border-bottom: 1px solid #eee;
        }

        .order-item:last-child {
            border-bottom: 0;
        }

    </style>

</head>


<body class="admin-shell admin-order-detail">


<?php require __DIR__ . '/navbar.php'; ?>



<!-- ============================================================= -->
<!-- CONTENT -->
<!-- ============================================================= -->

<main class="container py-5">


    <?php if ($adminFeedback): ?>
        <div class="alert alert-<?= htmlspecialchars($adminFeedback['type']) ?>" role="alert">
            <?= htmlspecialchars($adminFeedback['message']) ?>
        </div>
    <?php endif; ?>


    <a
        href="dashboard.php"
        class="text-decoration-none text-secondary d-inline-block mb-4"
    >
        ← Kembali ke Beranda
    </a>



    <div
        class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4"
    >

        <div>

            <small class="text-secondary">
                Kode Pesanan
            </small>

            <h2 class="fw-bold mb-1">

                <?= htmlspecialchars(
                    $order['order_code']
                ) ?>

            </h2>


            <div class="text-secondary">

                <?= date(
                    'd/m/Y H:i',
                    strtotime(
                        $order['created_at']
                    )
                ) ?>

            </div>

        </div>


        <div>

            <span
                class="badge <?= paymentStatusClass(
                    $order['payment_status']
                ) ?> fs-6"
            >

                <?= htmlspecialchars(
                    paymentStatusLabel(
                        $order['payment_status']
                    )
                ) ?>

            </span>

        </div>

    </div>



    <div class="row g-4">


        <!-- ===================================================== -->
        <!-- KIRI -->
        <!-- ===================================================== -->

        <div class="col-lg-8">


            <!-- CUSTOMER -->

            <div class="card detail-card shadow-sm mb-4">

                <div class="card-body p-4">


                    <h4 class="fw-bold mb-4">
                        Data Customer
                    </h4>


                    <div class="row g-3">


                        <div class="col-md-6">

                            <small class="text-secondary">
                                Nama
                            </small>

                            <div class="fw-semibold">

                                <?= htmlspecialchars(
                                    $order['customer_name']
                                ) ?>

                            </div>

                        </div>



                        <div class="col-md-6">

                            <small class="text-secondary">
                                Nomor HP
                            </small>

                            <div class="fw-semibold">

                                <?= htmlspecialchars(
                                    $order['phone']
                                ) ?>

                            </div>

                        </div>


                    </div>



                    <hr class="my-4">



                    <small class="text-secondary">
                        Catatan Customer
                    </small>


                    <div class="mt-1">

                        <?php if (
                            !empty($order['customer_note'])
                        ): ?>

                            <?= nl2br(
                                htmlspecialchars(
                                    $order['customer_note']
                                )
                            ) ?>

                        <?php else: ?>

                            <span class="text-secondary">
                                Tidak ada catatan.
                            </span>

                        <?php endif; ?>

                    </div>


                </div>

            </div>



            <!-- ITEM PESANAN -->

            <div class="card detail-card shadow-sm">

                <div class="card-body p-4">


                    <h4 class="fw-bold mb-3">
                        Menu yang Dipesan
                    </h4>



                    <?php if (!empty($orderItems)): ?>


                        <?php foreach (
                            $orderItems as $item
                        ): ?>


                            <div class="order-item">


                                <div
                                    class="d-flex justify-content-between gap-3"
                                >


                                    <div>

                                        <div class="fw-bold">

                                            <?= htmlspecialchars(
                                                $item[
                                                    'product_name_snapshot'
                                                ]
                                            ) ?>

                                        </div>


                                        <small class="text-secondary">

                                            <?= (int)
                                                $item['quantity']
                                            ?>

                                            ×

                                            Rp<?= number_format(
                                                $item['unit_price'],
                                                0,
                                                ',',
                                                '.'
                                            ) ?>

                                        </small>

                                    </div>



                                    <div class="fw-bold">

                                        Rp<?= number_format(
                                            $item['subtotal'],
                                            0,
                                            ',',
                                            '.'
                                        ) ?>

                                    </div>


                                </div>

                            </div>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <p class="text-secondary mb-0">
                            Tidak ada item pesanan.
                        </p>


                    <?php endif; ?>



                    <hr>


                    <div
                        class="d-flex justify-content-between"
                    >

                        <span>
                            Subtotal
                        </span>

                        <strong>

                            Rp<?= number_format(
                                $order['subtotal'],
                                0,
                                ',',
                                '.'
                            ) ?>

                        </strong>

                    </div>



                    <div
                        class="d-flex justify-content-between mt-2"
                    >

                        <span class="fw-bold">
                            Total
                        </span>

                        <span class="fs-4 fw-bold">

                            Rp<?= number_format(
                                $order['total'],
                                0,
                                ',',
                                '.'
                            ) ?>

                        </span>

                    </div>


                </div>

            </div>


        </div>



        <!-- ===================================================== -->
        <!-- KANAN -->
        <!-- ===================================================== -->

        <div class="col-lg-4">


            <!-- PEMBAYARAN -->

            <div class="card detail-card shadow-sm mb-4">

                <div class="card-body p-4">


                    <h5 class="fw-bold mb-3">
                        Pembayaran
                    </h5>


                    <div class="mb-3">

                        <small class="text-secondary">
                            Metode
                        </small>

                        <div class="fw-semibold">
                            <?= htmlspecialchars($order['payment_method'], ENT_QUOTES, 'UTF-8') ?>
                        </div>

                    </div>


                    <div class="mb-4">

                        <small class="text-secondary">
                            Status
                        </small>

                        <div class="mt-1">

                            <span
                                class="badge <?= paymentStatusClass(
                                    $order['payment_status']
                                ) ?>"
                            >

                                <?= htmlspecialchars(
                                    paymentStatusLabel(
                                        $order[
                                            'payment_status'
                                        ]
                                    )
                                ) ?>

                            </span>

                        </div>

                    </div>



                    <?php if (
                        $order['payment_status'] === 'waiting_confirmation'
                        || (
                            $order['payment_method'] === 'Tunai di Kasir'
                            && $order['payment_status'] === 'unpaid'
                        )
                    ): ?>


                        <form
                            action="update-order.php"
                            method="POST"
                        >

                            <input
                                type="hidden"
                                name="order_id"
                                value="<?= $order['id'] ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="confirm_payment"
                            >


                            <button
                                type="submit"
                                class="btn btn-success w-100 rounded-pill"
                            >
                                <?= $order['payment_method'] === 'Tunai di Kasir'
                                    ? 'Konfirmasi Pembayaran Tunai'
                                    : 'Konfirmasi Pembayaran' ?>
                            </button>

                        </form>


                    <?php elseif (
                        $order['payment_status']
                        === 'paid'
                    ): ?>


                        <div
                            class="alert alert-success mb-0"
                        >
                            Pembayaran sudah diverifikasi.
                        </div>


                    <?php endif; ?>


                </div>

            </div>



            <!-- STATUS PESANAN -->

            <div class="card detail-card shadow-sm">

                <div class="card-body p-4">


                    <h5 class="fw-bold mb-3">
                        Status Pesanan
                    </h5>


                    <form
                        action="update-order.php"
                        method="POST"
                    >


                        <input
                            type="hidden"
                            name="order_id"
                            value="<?= $order['id'] ?>"
                        >


                        <input
                            type="hidden"
                            name="action"
                            value="update_status"
                        >


                        <select
                            name="order_status"
                            class="form-select mb-3"
                            required
                        >


                            <?php foreach (
                                $orderStatuses as $status
                            ): ?>


                                <option
                                    value="<?= htmlspecialchars(
                                        $status
                                    ) ?>"
                                    <?= $order['order_status'] === $status
                                        ? 'selected'
                                        : ''
                                    ?>
                                >

                                    <?= htmlspecialchars(
                                        orderStatusLabel(
                                            $status
                                        )
                                    ) ?>

                                </option>


                            <?php endforeach; ?>


                        </select>


                        <button
                            type="submit"
                            class="btn btn-dark w-100 rounded-pill"
                        >
                            Simpan Status
                        </button>


                    </form>


                </div>

            </div>


        </div>


    </div>


</main>



<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


</body>

</html>