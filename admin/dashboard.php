<?php

session_start();

require_once __DIR__ . '/auth.php';
require_once '../config/navigation.php';
require '../config/database.php';


/*
|--------------------------------------------------------------------------
| Statistik dashboard
|--------------------------------------------------------------------------
*/

$statsQuery = $pdo->query("
    SELECT
        COUNT(*) AS total_orders,

        SUM(
            CASE
                WHEN order_status = 'pending'
                THEN 1
                ELSE 0
            END
        ) AS pending_orders,

        SUM(
            CASE
                WHEN payment_status IN ('waiting_confirmation', 'unpaid')
                THEN 1
                ELSE 0
            END
        ) AS waiting_payment,

        SUM(
            CASE
                WHEN payment_status = 'paid'
                THEN 1
                ELSE 0
            END
        ) AS paid_orders

    FROM orders
");

$stats = $statsQuery->fetch();

$erpStats = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM products) AS total_products,
        (SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()) AS orders_today,
        (
            SELECT COALESCE(SUM(COALESCE(payment_totals.amount, o.total)), 0)
            FROM orders o
            LEFT JOIN (
                SELECT order_id, MAX(amount) AS amount
                FROM payments
                WHERE status = 'paid'
                GROUP BY order_id
            ) payment_totals ON payment_totals.order_id = o.id
            WHERE o.payment_status = 'paid'
        ) AS paid_revenue
")->fetch();

$activeOrders = (int) $pdo->query("
    SELECT COUNT(*)
    FROM orders
    WHERE order_status NOT IN ('completed', 'cancelled')
")->fetchColumn();

$chartEndDate = new DateTimeImmutable('today');
$chartStartDate = $chartEndDate->modify('-6 days');
$chartStart = $chartStartDate->format('Y-m-d 00:00:00');
$chartEnd = $chartEndDate->modify('+1 day')->format('Y-m-d 00:00:00');

$chartQuery = $pdo->prepare("
    SELECT
        DATE(COALESCE(payment_totals.paid_at, o.updated_at, o.created_at)) AS sales_date,
        COUNT(*) AS transaction_count,
        SUM(COALESCE(payment_totals.amount, o.total)) AS revenue
    FROM orders o
    LEFT JOIN (
        SELECT order_id, MAX(paid_at) AS paid_at, MAX(amount) AS amount
        FROM payments
        WHERE status = 'paid'
        GROUP BY order_id
    ) payment_totals ON payment_totals.order_id = o.id
    WHERE o.payment_status = 'paid'
      AND COALESCE(payment_totals.paid_at, o.updated_at, o.created_at) >= ?
      AND COALESCE(payment_totals.paid_at, o.updated_at, o.created_at) < ?
    GROUP BY DATE(COALESCE(payment_totals.paid_at, o.updated_at, o.created_at))
    ORDER BY sales_date
");
$chartQuery->execute([$chartStart, $chartEnd]);
$dailySalesRows = $chartQuery->fetchAll();

$dailySales = [];
for ($dayOffset = 6; $dayOffset >= 0; $dayOffset--) {
    $day = $chartEndDate->modify('-' . $dayOffset . ' days');
    $dailySales[$day->format('Y-m-d')] = [
        'label' => $day->format('d/m'),
        'transaction_count' => 0,
        'revenue' => 0.0
    ];
}

foreach ($dailySalesRows as $dailySalesRow) {
    $salesDate = $dailySalesRow['sales_date'];
    if (isset($dailySales[$salesDate])) {
        $dailySales[$salesDate]['transaction_count'] = (int) $dailySalesRow['transaction_count'];
        $dailySales[$salesDate]['revenue'] = (float) $dailySalesRow['revenue'];
    }
}

$maxDailyTransactions = max(array_column($dailySales, 'transaction_count')) ?: 1;
$maxDailyRevenue = max(array_column($dailySales, 'revenue')) ?: 1;

$lowStockQuery = $pdo->query("
    SELECT id, name, stock
    FROM products
    WHERE COALESCE(stock, 0) <= 5
    ORDER BY COALESCE(stock, 0) ASC, name ASC
    LIMIT 8
");
$lowStockProducts = $lowStockQuery->fetchAll();

$bestSellerQuery = $pdo->query("
    SELECT
        oi.product_name_snapshot AS product_name,
        SUM(oi.quantity) AS quantity_sold
    FROM order_items oi
    INNER JOIN orders o ON o.id = oi.order_id
    WHERE o.payment_status = 'paid'
    GROUP BY oi.product_id, oi.product_name_snapshot
    ORDER BY quantity_sold DESC, product_name ASC
    LIMIT 5
");
$bestSellingProducts = $bestSellerQuery->fetchAll();


/*
|--------------------------------------------------------------------------
| Pesanan terbaru
|--------------------------------------------------------------------------
*/

$orderQuery = $pdo->query("
    SELECT
        id,
        order_code,
        customer_name,
        phone,
        total,
        order_status,
        payment_status,
        created_at

    FROM orders

    ORDER BY id DESC

    LIMIT 10
");

$orders = $orderQuery->fetchAll();


/*
|--------------------------------------------------------------------------
| Fungsi label status pembayaran
|--------------------------------------------------------------------------
*/

function paymentStatusLabel($status)
{

    switch ($status) {

        case 'waiting_confirmation':
            return 'Menunggu Verifikasi';

        case 'paid':
            return 'Lunas';

        case 'unpaid':
            return 'Menunggu Pembayaran';

        default:
            return ucfirst(
                str_replace('_', ' ', $status)
            );

    }

}


/*
|--------------------------------------------------------------------------
| Fungsi warna status pembayaran
|--------------------------------------------------------------------------
*/

function paymentStatusClass($status)
{

    switch ($status) {

        case 'waiting_confirmation':
        case 'unpaid':
            return 'text-bg-warning';

        case 'paid':
            return 'text-bg-success';

        case 'unpaid':
            return 'text-bg-secondary';

        default:
            return 'text-bg-light';

    }

}


/*
|--------------------------------------------------------------------------
| Fungsi status pesanan
|--------------------------------------------------------------------------
*/

function orderStatusLabel($status)
{

    switch ($status) {

        case 'pending':
            return 'Baru';

        case 'confirmed':
            return 'Dikonfirmasi';

        case 'preparing':
            return 'Diproses';

        case 'ready':
            return 'Siap';

        default:
            return ucfirst(
                str_replace('_', ' ', $status)
            );

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
        Beranda Admin - WASIBU
    </title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >


</head>


<body class="admin-shell admin-dashboard">


<?php require __DIR__ . '/navbar.php'; ?>



<!-- ============================================================= -->
<!-- DASHBOARD -->
<!-- ============================================================= -->

<main class="container py-5">


    <!-- HEADER -->

    <div class="dashboard-page-heading">

        <h2 class="fw-bold mb-1">
            Wasibu - Warung Si Bungsu
        </h2>

        <p class="text-secondary mb-0">
            Pantau aktivitas penjualan, pesanan, dan stok WASIBU.
        </p>

    </div>



    <!-- ========================================================= -->
    <!-- STATISTIK -->
    <!-- ========================================================= -->

    <div class="row g-3 mb-5 dashboard-stat-grid">


        <!-- PESANAN AKTIF -->

        <div class="col-6 col-lg-3">

            <div class="card admin-stat-card shadow-sm">

                <div class="card-body p-4">

                    <div class="text-secondary mb-2">
                        Pesanan Aktif
                    </div>

                    <div class="admin-stat-number">

                        <?= $activeOrders ?>

                    </div>

                </div>

            </div>

        </div>



        <!-- PESANAN BARU -->

        <div class="col-6 col-lg-3">

            <div class="card admin-stat-card shadow-sm">

                <div class="card-body p-4">

                    <div class="text-secondary mb-2">
                        Pesanan Baru
                    </div>

                    <div class="admin-stat-number">

                        <?= (int) (
                            $stats['pending_orders'] ?? 0
                        ) ?>

                    </div>

                </div>

            </div>

        </div>



        <!-- MENUNGGU VERIFIKASI -->

        <div class="col-6 col-lg-3">

            <div class="card admin-stat-card shadow-sm">

                <div class="card-body p-4">

                    <div class="text-secondary mb-2">
                        Menunggu Bayar
                    </div>

                    <div class="admin-stat-number">

                        <?= (int) (
                            $stats['waiting_payment'] ?? 0
                        ) ?>

                    </div>

                </div>

            </div>

        </div>



        <!-- LUNAS -->

        <div class="col-6 col-lg-3">

            <div class="card admin-stat-card shadow-sm">

                <div class="card-body p-4">

                    <div class="text-secondary mb-2">
                        Terverifikasi
                    </div>

                    <div class="admin-stat-number">

                        <?= (int) (
                            $stats['paid_orders'] ?? 0
                        ) ?>

                    </div>

                </div>

            </div>

        </div>


    </div>



    <div class="row g-3 mb-5 dashboard-stat-grid">
        <div class="col-6 col-lg-3">
            <div class="card admin-stat-card shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="text-secondary mb-2">Total Produk</div>
                    <div class="admin-stat-number"><?= (int) $erpStats['total_products'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card admin-stat-card shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="text-secondary mb-2">Total Pesanan</div>
                    <div class="admin-stat-number"><?= (int) $stats['total_orders'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card admin-stat-card shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="text-secondary mb-2">Pendapatan Terbayar</div>
                    <div class="fw-bold fs-4">Rp<?= number_format((float) $erpStats['paid_revenue'], 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card admin-stat-card shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="text-secondary mb-2">Pesanan Hari Ini</div>
                    <div class="admin-stat-number"><?= (int) $erpStats['orders_today'] ?></div>
                </div>
            </div>
        </div>
    </div>

    <nav class="dashboard-actions dashboard-section" aria-label="Navigasi dashboard">
        <a href="products.php" class="btn btn-dark rounded-pill px-4">Menu WASIBU</a>
        <a href="orders.php" class="btn btn-outline-dark rounded-pill px-4">Lihat Pesanan</a>
        <a href="reports.php" class="btn btn-outline-dark rounded-pill px-4">Laporan</a>
    </nav>

    <section class="row g-3 dashboard-section" aria-label="Grafik penjualan tujuh hari terakhir">
        <div class="col-lg-6">
            <div class="card dashboard-chart-card shadow-sm h-100">
                <div class="card-body p-4">
                    <h3 class="h5 fw-bold mb-1">Jumlah Transaksi</h3>
                    <p class="small text-secondary mb-4">Transaksi lunas, tujuh hari terakhir.</p>
                    <div class="dashboard-chart-list">
                        <?php foreach ($dailySales as $dayData): ?>
                            <?php $barWidth = round($dayData['transaction_count'] / $maxDailyTransactions * 100, 1); ?>
                            <div class="dashboard-chart-row">
                                <span class="small text-secondary"><?= htmlspecialchars($dayData['label']) ?></span>
                                <div class="dashboard-chart-track" role="img" aria-label="<?= (int) $dayData['transaction_count'] ?> transaksi">
                                    <span class="dashboard-chart-bar" style="width: <?= $barWidth ?>%"></span>
                                </div>
                                <strong class="dashboard-chart-value"><?= (int) $dayData['transaction_count'] ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dashboard-chart-card shadow-sm h-100">
                <div class="card-body p-4">
                    <h3 class="h5 fw-bold mb-1">Pendapatan Harian</h3>
                    <p class="small text-secondary mb-4">Pembayaran terkonfirmasi, tujuh hari terakhir.</p>
                    <div class="dashboard-chart-list">
                        <?php foreach ($dailySales as $dayData): ?>
                            <?php $barWidth = round($dayData['revenue'] / $maxDailyRevenue * 100, 1); ?>
                            <div class="dashboard-chart-row">
                                <span class="small text-secondary"><?= htmlspecialchars($dayData['label']) ?></span>
                                <div class="dashboard-chart-track" role="img" aria-label="Pendapatan Rp<?= number_format($dayData['revenue'], 0, ',', '.') ?>">
                                    <span class="dashboard-chart-bar" style="width: <?= $barWidth ?>%"></span>
                                </div>
                                <strong class="dashboard-chart-value">Rp<?= number_format($dayData['revenue'], 0, ',', '.') ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="row g-3 dashboard-section">
        <div class="col-lg-6">
            <section class="card admin-order-card shadow-sm h-100">
                <div class="card-body p-4">
                    <h4 class="fw-bold mb-1">Stok Rendah</h4>
                    <p class="text-secondary small">Produk dengan stok 5 atau kurang.</p>
                    <?php if ($lowStockProducts): ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle dashboard-data-table mb-0">
                                <thead><tr><th>Produk</th><th class="text-end">Stok</th></tr></thead>
                                <tbody>
                                <?php foreach ($lowStockProducts as $product): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($product['name']) ?></td>
                                        <td class="text-end"><span class="badge <?= (int) $product['stock'] === 0 ? 'text-bg-danger' : 'text-bg-warning' ?>"><?= (int) $product['stock'] ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-secondary mb-0">Tidak ada produk dengan stok rendah.</p>
                    <?php endif; ?>
                </div>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="card admin-order-card shadow-sm h-100">
                <div class="card-body p-4">
                    <h4 class="fw-bold mb-1">Produk Terlaris</h4>
                    <p class="text-secondary small">Berdasarkan kuantitas pesanan berstatus lunas.</p>
                    <?php if ($bestSellingProducts): ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle dashboard-data-table mb-0">
                                <thead><tr><th>Produk</th><th class="text-end">Terjual</th></tr></thead>
                                <tbody>
                                <?php foreach ($bestSellingProducts as $product): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($product['product_name']) ?></td>
                                        <td class="text-end"><?= (int) $product['quantity_sold'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-secondary mb-0">Belum ada produk terjual pada pesanan lunas.</p>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- PESANAN TERBARU -->
    <!-- ========================================================= -->

    <div class="card admin-order-card dashboard-section shadow-sm">


        <div class="card-body p-4">


            <div
                class="d-flex justify-content-between align-items-center mb-4"
            >

            <a
                href="orders.php"
                class="btn btn-outline-dark rounded-pill"
            >
                Lihat Semua
            </a>

                <div>
                    <h4 class="fw-bold mb-1">Pesanan Terbaru</h4>

                    <small class="text-secondary">
                        10 pesanan terakhir.
                    </small>

                </div>

            </div>



            <?php if (!empty($orders)): ?>


                <div class="table-responsive">


                    <table
                        class="table admin-table dashboard-order-table align-middle"
                    >


                        <thead>

                            <tr>

                                <th>
                                    Customer
                                </th>

                                <th>
                                    No. HP
                                </th>

                                <th>
                                    Total
                                </th>

                                <th>
                                    Pesanan
                                </th>

                                <th>
                                    Pembayaran
                                </th>

                                <th>
                                    Waktu
                                </th>

                                <th>
                                    Aksi
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach ($orders as $order): ?>


                            <tr>


                                <td>

                                    <?= htmlspecialchars(
                                        $order['customer_name']
                                    ) ?>

                                </td>



                                <td>

                                    <?= htmlspecialchars(
                                        $order['phone']
                                    ) ?>

                                </td>



                                <td>

                                    <strong>

                                        Rp<?= number_format(
                                            $order['total'],
                                            0,
                                            ',',
                                            '.'
                                        ) ?>

                                    </strong>

                                </td>



                                <td>

                                    <span
                                        class="badge text-bg-dark"
                                    >

                                        <?= htmlspecialchars(
                                            orderStatusLabel(
                                                $order['order_status']
                                            )
                                        ) ?>

                                    </span>

                                </td>



                                <td>

                                    <span
                                        class="badge <?= paymentStatusClass(
                                            $order['payment_status']
                                        ) ?>"
                                    >

                                        <?= htmlspecialchars(
                                            paymentStatusLabel(
                                                $order['payment_status']
                                            )
                                        ) ?>

                                    </span>

                                </td>



                                <td>

                                    <small class="text-secondary">

                                        <?= date(
                                            'd/m/Y H:i',
                                            strtotime(
                                                $order['created_at']
                                            )
                                        ) ?>

                                    </small>

                                </td>


                                <td>

                                    <a
                                        href="order-detail.php?id=<?= $order['id'] ?>"
                                        class="btn btn-sm btn-dark rounded-pill px-3"
                                    >
                                        Lihat Detail
                                    </a>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>


                    </table>


                </div>


            <?php else: ?>


                <div class="text-center py-5">


                    <div class="fs-1 mb-3">
                        📋
                    </div>


                    <h5 class="fw-bold">
                        Belum ada pesanan
                    </h5>


                    <p class="text-secondary mb-0">
                        Pesanan customer akan muncul di sini.
                    </p>


                </div>


            <?php endif; ?>


        </div>

    </div>


</main>



<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


</body>

</html>