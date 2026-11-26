<?php

session_start();

require_once __DIR__ . '/auth.php';
require_once '../config/navigation.php';
require '../config/database.php';

$reportType = ($_GET['type'] ?? 'daily') === 'monthly' ? 'monthly' : 'daily';
$selectedDate = isset($_GET['date']) && is_string($_GET['date'])
    ? $_GET['date']
    : date('Y-m-d');
$dateObject = DateTime::createFromFormat('!Y-m-d', $selectedDate);
if (!$dateObject || $dateObject->format('Y-m-d') !== $selectedDate) {
    $selectedDate = date('Y-m-d');
    $dateObject = new DateTime($selectedDate);
}

$selectedMonth = isset($_GET['month']) && is_string($_GET['month'])
    ? $_GET['month']
    : date('Y-m');
$monthObject = DateTime::createFromFormat('!Y-m', $selectedMonth);
if (!$monthObject || $monthObject->format('Y-m') !== $selectedMonth) {
    $selectedMonth = date('Y-m');
    $monthObject = new DateTime(date('Y-m-01'));
}

if ($reportType === 'daily') {
    $rangeStart = $dateObject->format('Y-m-d 00:00:00');
    $rangeEnd = (clone $dateObject)->modify('+1 day')->format('Y-m-d 00:00:00');
    $periodLabel = $dateObject->format('d/m/Y');
} else {
    $rangeStart = $monthObject->format('Y-m-01 00:00:00');
    $rangeEnd = (clone $monthObject)->modify('first day of next month')->format('Y-m-01 00:00:00');
    $monthNames = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    $periodLabel = $monthNames[(int) $monthObject->format('n')] . ' ' . $monthObject->format('Y');
}

$salesStmt = $pdo->prepare("
    SELECT
        DATE(COALESCE(payment_times.paid_at, o.updated_at, o.created_at)) AS sales_date,
        COUNT(DISTINCT o.id) AS transaction_count,
        SUM(COALESCE(payment_times.amount, o.total)) AS revenue
    FROM orders o
    LEFT JOIN (
        SELECT order_id, MAX(paid_at) AS paid_at, MAX(amount) AS amount
        FROM payments
        WHERE status = 'paid'
        GROUP BY order_id
    ) payment_times ON payment_times.order_id = o.id
    WHERE o.payment_status = 'paid'
      AND COALESCE(payment_times.paid_at, o.updated_at, o.created_at) >= ?
      AND COALESCE(payment_times.paid_at, o.updated_at, o.created_at) < ?
    GROUP BY DATE(COALESCE(payment_times.paid_at, o.updated_at, o.created_at))
    ORDER BY sales_date DESC
");
$salesStmt->execute([$rangeStart, $rangeEnd]);
$salesRows = $salesStmt->fetchAll();

$reportTransactions = 0;
$reportRevenue = 0.0;
foreach ($salesRows as $salesRow) {
    $reportTransactions += (int) $salesRow['transaction_count'];
    $reportRevenue += (float) $salesRow['revenue'];
}

$detailStmt = $pdo->prepare("
    SELECT
        COALESCE(payment_times.paid_at, o.updated_at, o.created_at) AS transaction_date,
        o.order_code,
        o.customer_name,
        COALESCE(payment_times.amount, o.total) AS payment_total,
        o.payment_status
    FROM orders o
    LEFT JOIN (
        SELECT order_id, MAX(paid_at) AS paid_at, MAX(amount) AS amount
        FROM payments
        WHERE status = 'paid'
        GROUP BY order_id
    ) payment_times ON payment_times.order_id = o.id
    WHERE o.payment_status = 'paid'
      AND COALESCE(payment_times.paid_at, o.updated_at, o.created_at) >= ?
      AND COALESCE(payment_times.paid_at, o.updated_at, o.created_at) < ?
    ORDER BY transaction_date DESC, o.id DESC
");
$detailStmt->execute([$rangeStart, $rangeEnd]);
$transactionDetails = $detailStmt->fetchAll();

$bestSellerStmt = $pdo->query("
    SELECT
        oi.product_name_snapshot AS product_name,
        SUM(oi.quantity) AS quantity_sold
    FROM order_items oi
    INNER JOIN orders o ON o.id = oi.order_id
    WHERE o.payment_status = 'paid'
    GROUP BY oi.product_id, oi.product_name_snapshot
    ORDER BY quantity_sold DESC, product_name ASC
    LIMIT 10
");
$bestSellingProducts = $bestSellerStmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan ERP - WASIBU Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-shell admin-reports">
<?php require __DIR__ . '/navbar.php'; ?>

<main class="container py-5">
    <div class="reports-page-heading d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <div class="fw-semibold">WASIBU - Warung Si Bungsu</div>
            <h2 class="fw-bold mb-1">Laporan Penjualan</h2>
            <p class="reports-period mb-0">Periode: <?= htmlspecialchars($periodLabel) ?></p>
        </div>
        <?php $printParameters = ['type' => $reportType] + ($reportType === 'daily' ? ['date' => $selectedDate] : ['month' => $selectedMonth]); ?>
        <a href="print-report.php?<?= htmlspecialchars(http_build_query($printParameters)) ?>" class="btn btn-outline-dark rounded-pill">Cetak Laporan</a>
    </div>

    <form method="GET" class="row g-3 align-items-end mb-4">
        <div class="col-sm-4 col-lg-3">
            <label for="type" class="form-label">Periode</label>
            <select id="type" name="type" class="form-select" onchange="this.form.submit()">
                <option value="daily" <?= $reportType === 'daily' ? 'selected' : '' ?>>Harian</option>
                <option value="monthly" <?= $reportType === 'monthly' ? 'selected' : '' ?>>Bulanan</option>
            </select>
        </div>
        <?php if ($reportType === 'daily'): ?>
            <div class="col-sm-5 col-lg-3">
                <label for="date" class="form-label">Tanggal</label>
                <input id="date" type="date" name="date" class="form-control" value="<?= htmlspecialchars($selectedDate) ?>">
            </div>
        <?php else: ?>
            <div class="col-sm-5 col-lg-3">
                <label for="month" class="form-label">Bulan</label>
                <input id="month" type="month" name="month" class="form-control" value="<?= htmlspecialchars($selectedMonth) ?>">
            </div>
        <?php endif; ?>
        <div class="col-sm-3 col-lg-2">
            <button type="submit" class="btn btn-dark w-100">Tampilkan</button>
        </div>
    </form>

    <section class="card admin-order-card shadow-sm mb-5">
        <div class="card-body p-4">
            <h4 class="fw-bold mb-3">Ringkasan <?= $reportType === 'daily' ? 'Harian' : 'Bulanan' ?></h4>
            <div class="row g-3 mb-4">
                <div class="col-sm-6">
                    <div class="text-secondary">Jumlah Transaksi</div>
                    <div class="admin-stat-number"><?= $reportTransactions ?></div>
                </div>
                <div class="col-sm-6">
                    <div class="text-secondary">Total Pendapatan</div>
                    <div class="fw-bold fs-4">Rp<?= number_format($reportRevenue, 0, ',', '.') ?></div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th class="text-end">Jumlah Transaksi</th>
                            <th class="text-end">Total Pendapatan</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($salesRows): ?>
                        <?php foreach ($salesRows as $row): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($row['sales_date'])) ?></td>
                                <td class="text-end"><?= (int) $row['transaction_count'] ?></td>
                                <td class="text-end">Rp<?= number_format((float) $row['revenue'], 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="text-center text-secondary py-4">Tidak ada transaksi lunas pada periode ini.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="card admin-order-card shadow-sm mb-5">
        <div class="card-body p-4">
            <h4 class="fw-bold mb-3">Detail Transaksi</h4>
            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Tanggal Transaksi</th>
                            <th>Kode Order / Invoice</th>
                            <th>Nama Customer</th>
                            <th class="text-end">Total Pembayaran</th>
                            <th>Status Pembayaran</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($transactionDetails): ?>
                        <?php foreach ($transactionDetails as $transaction): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($transaction['transaction_date'])) ?></td>
                                <td><?= htmlspecialchars($transaction['order_code']) ?></td>
                                <td><?= htmlspecialchars($transaction['customer_name']) ?></td>
                                <td class="text-end">Rp<?= number_format((float) $transaction['payment_total'], 0, ',', '.') ?></td>
                                <td><span class="badge text-bg-success">PAID</span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center text-secondary py-4">Tidak ada transaksi lunas pada periode ini.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="card admin-order-card shadow-sm">
        <div class="card-body p-4">
            <h4 class="fw-bold mb-1">Produk Terlaris</h4>
            <p class="text-secondary small">Berdasarkan total kuantitas pada pesanan lunas.</p>
            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead><tr><th>Produk</th><th class="text-end">Jumlah Terjual</th></tr></thead>
                    <tbody>
                    <?php if ($bestSellingProducts): ?>
                        <?php foreach ($bestSellingProducts as $product): ?>
                            <tr>
                                <td><?= htmlspecialchars($product['product_name']) ?></td>
                                <td class="text-end"><?= (int) $product['quantity_sold'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="2" class="text-center text-secondary py-4">Belum ada data penjualan.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>
</body>
</html>