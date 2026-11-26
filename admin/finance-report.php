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
$dateErrors = DateTime::getLastErrors();
if (
    !$dateObject
    || ($dateErrors && ($dateErrors['warning_count'] || $dateErrors['error_count']))
    || $dateObject->format('Y-m-d') !== $selectedDate
) {
    $selectedDate = date('Y-m-d');
    $dateObject = new DateTime($selectedDate);
}

$selectedMonth = isset($_GET['month']) && is_string($_GET['month'])
    ? $_GET['month']
    : date('Y-m');
$monthObject = DateTime::createFromFormat('!Y-m', $selectedMonth);
$monthErrors = DateTime::getLastErrors();
if (
    !$monthObject
    || ($monthErrors && ($monthErrors['warning_count'] || $monthErrors['error_count']))
    || $monthObject->format('Y-m') !== $selectedMonth
) {
    $selectedMonth = date('Y-m');
    $monthObject = new DateTime(date('Y-m-01'));
}

$monthNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

if ($reportType === 'daily') {
    $rangeStart = $dateObject->format('Y-m-d 00:00:00');
    $rangeEnd = (clone $dateObject)->modify('+1 day')->format('Y-m-d 00:00:00');
    $periodLabel = $dateObject->format('d/m/Y');
} else {
    $rangeStart = $monthObject->format('Y-m-01 00:00:00');
    $rangeEnd = (clone $monthObject)->modify('first day of next month')->format('Y-m-01 00:00:00');
    $periodLabel = $monthNames[(int) $monthObject->format('n')] . ' ' . $monthObject->format('Y');
}

$paidPaymentJoin = "
    LEFT JOIN (
        SELECT order_id, MAX(id) AS payment_id
        FROM payments
        WHERE status = 'paid'
        GROUP BY order_id
    ) paid_lookup ON paid_lookup.order_id = o.id
    LEFT JOIN payments p ON p.id = paid_lookup.payment_id
";
$paidAtExpression = 'COALESCE(p.paid_at, o.updated_at, o.created_at)';
$paymentMethodExpression = "COALESCE(p.method, 'QRIS')";
$validPaidCondition = "
    (
        paid_lookup.payment_id IS NOT NULL
        OR NOT EXISTS (
            SELECT 1
            FROM payments any_payment
            WHERE any_payment.order_id = o.id
        )
    )
";

$summaryStmt = $pdo->prepare("
    SELECT
        COUNT(o.id) AS paid_count,
        COALESCE(SUM(o.total), 0) AS revenue,
        COALESCE(SUM(CASE WHEN $paymentMethodExpression = 'Tunai di Kasir' THEN o.total ELSE 0 END), 0) AS cash_revenue,
        COALESCE(SUM(CASE WHEN $paymentMethodExpression <> 'Tunai di Kasir' THEN o.total ELSE 0 END), 0) AS qris_revenue
    FROM orders o
    $paidPaymentJoin
    WHERE o.payment_status = 'paid'
      AND $validPaidCondition
      AND $paidAtExpression >= ?
      AND $paidAtExpression < ?
");
$summaryStmt->execute([$rangeStart, $rangeEnd]);
$summary = $summaryStmt->fetch();

$unpaidStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS unpaid_count,
        COALESCE(SUM(total), 0) AS unpaid_total
    FROM orders
    WHERE payment_status IN ('unpaid', 'waiting_confirmation', 'failed')
      AND order_status <> 'cancelled'
      AND created_at >= ?
      AND created_at < ?
");
$unpaidStmt->execute([$rangeStart, $rangeEnd]);
$unpaidSummary = $unpaidStmt->fetch();

$detailStmt = $pdo->prepare("
    SELECT
        $paidAtExpression AS transaction_date,
        o.order_code,
        o.customer_name,
        o.total AS payment_total,
        $paymentMethodExpression AS payment_method
    FROM orders o
    $paidPaymentJoin
    WHERE o.payment_status = 'paid'
      AND $validPaidCondition
      AND $paidAtExpression >= ?
      AND $paidAtExpression < ?
    ORDER BY transaction_date DESC, o.id DESC
");
$detailStmt->execute([$rangeStart, $rangeEnd]);
$paidTransactions = $detailStmt->fetchAll();

$unpaidDetailsStmt = $pdo->prepare("
    SELECT
        o.created_at,
        o.order_code,
        o.customer_name,
        o.total,
        o.payment_status,
        COALESCE(latest_payment.method, 'QRIS') AS payment_method
    FROM orders o
    LEFT JOIN (
        SELECT order_id, MAX(id) AS payment_id
        FROM payments
        GROUP BY order_id
    ) latest_lookup ON latest_lookup.order_id = o.id
    LEFT JOIN payments latest_payment ON latest_payment.id = latest_lookup.payment_id
    WHERE o.payment_status IN ('unpaid', 'waiting_confirmation', 'failed')
      AND o.order_status <> 'cancelled'
      AND o.created_at >= ?
      AND o.created_at < ?
    ORDER BY o.created_at DESC, o.id DESC
");
$unpaidDetailsStmt->execute([$rangeStart, $rangeEnd]);
$unpaidTransactions = $unpaidDetailsStmt->fetchAll();

$dailyStart = new DateTimeImmutable('today -6 days');
$dailyEnd = new DateTimeImmutable('tomorrow');
$dailyChartStmt = $pdo->prepare("
    SELECT
        DATE($paidAtExpression) AS period_key,
        SUM(o.total) AS revenue
    FROM orders o
    $paidPaymentJoin
    WHERE o.payment_status = 'paid'
      AND $validPaidCondition
      AND $paidAtExpression >= ?
      AND $paidAtExpression < ?
    GROUP BY DATE($paidAtExpression)
");
$dailyChartStmt->execute([
    $dailyStart->format('Y-m-d 00:00:00'),
    $dailyEnd->format('Y-m-d 00:00:00')
]);
$dailyChartRows = $dailyChartStmt->fetchAll();
$dailyChart = [];
for ($offset = 6; $offset >= 0; $offset--) {
    $day = $dailyEnd->modify('-' . ($offset + 1) . ' days');
    $dailyChart[$day->format('Y-m-d')] = [
        'label' => $day->format('d/m'),
        'revenue' => 0.0
    ];
}
foreach ($dailyChartRows as $row) {
    if (isset($dailyChart[$row['period_key']])) {
        $dailyChart[$row['period_key']]['revenue'] = (float) $row['revenue'];
    }
}

$monthlyStart = new DateTimeImmutable('first day of this month -5 months');
$monthlyEnd = new DateTimeImmutable('first day of next month');
$monthlyChartStmt = $pdo->prepare("
    SELECT
        DATE_FORMAT($paidAtExpression, '%Y-%m') AS period_key,
        SUM(o.total) AS revenue
    FROM orders o
    $paidPaymentJoin
    WHERE o.payment_status = 'paid'
      AND $validPaidCondition
      AND $paidAtExpression >= ?
      AND $paidAtExpression < ?
    GROUP BY DATE_FORMAT($paidAtExpression, '%Y-%m')
");
$monthlyChartStmt->execute([
    $monthlyStart->format('Y-m-01 00:00:00'),
    $monthlyEnd->format('Y-m-01 00:00:00')
]);
$monthlyChartRows = $monthlyChartStmt->fetchAll();
$monthlyChart = [];
for ($offset = 5; $offset >= 0; $offset--) {
    $month = $monthlyEnd->modify('-' . ($offset + 1) . ' months');
    $monthKey = $month->format('Y-m');
    $monthlyChart[$monthKey] = [
        'label' => $monthNames[(int) $month->format('n')] . ' ' . $month->format('Y'),
        'revenue' => 0.0
    ];
}
foreach ($monthlyChartRows as $row) {
    if (isset($monthlyChart[$row['period_key']])) {
        $monthlyChart[$row['period_key']]['revenue'] = (float) $row['revenue'];
    }
}

$maxDailyRevenue = max(array_column($dailyChart, 'revenue')) ?: 1;
$maxMonthlyRevenue = max(array_column($monthlyChart, 'revenue')) ?: 1;

function financeStatusLabel(string $status): string
{
    $labels = [
        'unpaid' => 'Menunggu Pembayaran',
        'waiting_confirmation' => 'Menunggu Verifikasi',
        'failed' => 'Gagal'
    ];

    return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan - WASIBU Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-shell admin-finance-report">
<?php require __DIR__ . '/navbar.php'; ?>

<main class="container py-5">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <div class="fw-semibold">WASIBU - Warung Si Bungsu</div>
            <h1 class="h2 fw-bold mb-1">Laporan Keuangan</h1>
            <p class="text-secondary mb-0">Laporan pemasukan/omzet, bukan laba bersih.</p>
        </div>
        <button type="button" class="btn btn-outline-dark rounded-pill finance-print-button" onclick="window.print()">Cetak Laporan</button>
    </div>

    <form method="GET" class="row g-3 align-items-end mb-4 finance-filter">
        <div class="col-sm-4 col-lg-3">
            <label for="type" class="form-label">Periode</label>
            <select id="type" name="type" class="form-select" onchange="this.form.submit()">
                <option value="daily" <?= $reportType === 'daily' ? 'selected' : '' ?>>Harian</option>
                <option value="monthly" <?= $reportType === 'monthly' ? 'selected' : '' ?>>Bulanan</option>
            </select>
        </div>
        <?php if ($reportType === 'daily'): ?>
            <div class="col-sm-5 col-lg-3">
                <label for="date" class="form-label">Tanggal transaksi</label>
                <input id="date" type="date" name="date" class="form-control" value="<?= htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8') ?>">
            </div>
        <?php else: ?>
            <div class="col-sm-5 col-lg-3">
                <label for="month" class="form-label">Bulan transaksi</label>
                <input id="month" type="month" name="month" class="form-control" value="<?= htmlspecialchars($selectedMonth, ENT_QUOTES, 'UTF-8') ?>">
            </div>
        <?php endif; ?>
        <div class="col-sm-3 col-lg-2">
            <button type="submit" class="btn btn-dark w-100">Tampilkan</button>
        </div>
    </form>

    <p class="text-secondary mb-3">Periode laporan: <?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?></p>

    <section class="row g-3 mb-4 finance-summary">
        <div class="col-6 col-xl-3">
            <div class="card admin-order-card h-100"><div class="card-body p-4">
                <div class="text-secondary">Omzet lunas</div>
                <div class="fw-bold fs-4">Rp<?= number_format((float) $summary['revenue'], 0, ',', '.') ?></div>
            </div></div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card admin-order-card h-100"><div class="card-body p-4">
                <div class="text-secondary">Transaksi lunas</div>
                <div class="admin-stat-number"><?= (int) $summary['paid_count'] ?></div>
            </div></div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card admin-order-card h-100"><div class="card-body p-4">
                <div class="text-secondary">Omzet QRIS</div>
                <div class="fw-bold fs-4">Rp<?= number_format((float) $summary['qris_revenue'], 0, ',', '.') ?></div>
            </div></div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card admin-order-card h-100"><div class="card-body p-4">
                <div class="text-secondary">Omzet tunai</div>
                <div class="fw-bold fs-4">Rp<?= number_format((float) $summary['cash_revenue'], 0, ',', '.') ?></div>
            </div></div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card admin-order-card h-100"><div class="card-body p-4">
                <div class="text-secondary">Pesanan belum dibayar</div>
                <div class="admin-stat-number"><?= (int) $unpaidSummary['unpaid_count'] ?></div>
            </div></div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card admin-order-card h-100"><div class="card-body p-4">
                <div class="text-secondary">Nominal belum dibayar</div>
                <div class="fw-bold fs-4">Rp<?= number_format((float) $unpaidSummary['unpaid_total'], 0, ',', '.') ?></div>
            </div></div>
        </div>
    </section>

    <section class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card admin-order-card h-100">
                <div class="card-body p-4">
                    <h2 class="h5 fw-bold mb-3">Pemasukan Harian — 7 Hari Terakhir</h2>
                    <div class="finance-chart" role="list">
                        <?php foreach ($dailyChart as $entry): ?>
                            <?php $width = $maxDailyRevenue > 0 ? ((float) $entry['revenue'] / $maxDailyRevenue) * 100 : 0; ?>
                            <div class="finance-chart-row" role="listitem">
                                <span><?= htmlspecialchars($entry['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                <div class="finance-chart-track"><div class="finance-chart-bar" style="width: <?= number_format($width, 2, '.', '') ?>%"></div></div>
                                <strong>Rp<?= number_format((float) $entry['revenue'], 0, ',', '.') ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card admin-order-card h-100">
                <div class="card-body p-4">
                    <h2 class="h5 fw-bold mb-3">Pemasukan Bulanan — 6 Bulan Terakhir</h2>
                    <div class="finance-chart" role="list">
                        <?php foreach ($monthlyChart as $entry): ?>
                            <?php $width = $maxMonthlyRevenue > 0 ? ((float) $entry['revenue'] / $maxMonthlyRevenue) * 100 : 0; ?>
                            <div class="finance-chart-row" role="listitem">
                                <span><?= htmlspecialchars($entry['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                <div class="finance-chart-track"><div class="finance-chart-bar" style="width: <?= number_format($width, 2, '.', '') ?>%"></div></div>
                                <strong>Rp<?= number_format((float) $entry['revenue'], 0, ',', '.') ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="card admin-order-card mb-4">
        <div class="card-body p-4">
            <h2 class="h5 fw-bold mb-3">Rincian Transaksi Lunas</h2>
            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead><tr><th>Tanggal bayar</th><th>Kode pesanan</th><th>Nama pelanggan</th><th>Metode</th><th class="text-end">Total akhir</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($paidTransactions as $transaction): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($transaction['transaction_date'])) ?></td>
                            <td><?= htmlspecialchars($transaction['order_code'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($transaction['customer_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($transaction['payment_method'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-end">Rp<?= number_format((float) $transaction['payment_total'], 0, ',', '.') ?></td>
                            <td><span class="badge text-bg-success">Lunas</span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$paidTransactions): ?>
                        <tr><td colspan="6" class="text-center text-secondary py-4">Tidak ada transaksi lunas pada periode ini.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="card admin-order-card">
        <div class="card-body p-4">
            <h2 class="h5 fw-bold mb-3">Rincian Pesanan Belum Dibayar</h2>
            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead><tr><th>Tanggal pesanan</th><th>Kode pesanan</th><th>Nama pelanggan</th><th>Metode</th><th class="text-end">Total</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($unpaidTransactions as $transaction): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($transaction['created_at'])) ?></td>
                            <td><?= htmlspecialchars($transaction['order_code'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($transaction['customer_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($transaction['payment_method'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-end">Rp<?= number_format((float) $transaction['total'], 0, ',', '.') ?></td>
                            <td><?= htmlspecialchars(financeStatusLabel($transaction['payment_status']), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$unpaidTransactions): ?>
                        <tr><td colspan="6" class="text-center text-secondary py-4">Tidak ada pesanan belum dibayar pada periode ini.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>
</body>
</html>
