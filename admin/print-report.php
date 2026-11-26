<?php

session_start();

require_once __DIR__ . '/auth.php';
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

$printMonthNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$printedAt = date('j') . ' ' . $printMonthNames[(int) date('n')] . ' ' . date('Y');
$reportParameters = ['type' => $reportType] + ($reportType === 'daily'
    ? ['date' => $selectedDate]
    : ['month' => $selectedMonth]);
$reportUrl = 'reports.php?' . http_build_query($reportParameters);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Laporan Penjualan - WASIBU</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <style>
        :root { color-scheme: light; }
        body { margin: 0; background: #FFF7F5; color: #202124; font: 14px/1.5 "DM Sans", Arial, sans-serif; }
        .report-paper { box-sizing: border-box; width: calc(100% - 32px); max-width: 800px; min-height: 1120px; margin: 32px auto; padding: 40px; background: #fff; box-shadow: 0 14px 42px rgba(65, 7, 9, 0.12); }
        .report-header { display: flex; align-items: center; gap: 22px; padding-bottom: 20px; border-bottom: 2px solid #202124; }
        .report-logo { display: block; width: 88px; height: 72px; flex: 0 0 auto; object-fit: contain; }
        .report-heading { min-width: 0; }
        .brand { margin: 0; font-size: 20px; font-weight: 700; }
        h1, h2 { font-family: "DM Serif Display", Georgia, serif; font-weight: 400; }
        h1 { margin: 6px 0 0; font-size: 26px; }
        .subtitle, .period { margin: 4px 0 0; color: #5f6368; }
        h2 { margin: 28px 0 10px; font-size: 17px; }
        .summary { display: flex; gap: 48px; padding: 14px 0; }
        .summary-label { display: block; color: #5f6368; }
        .summary-value { font-size: 20px; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 9px 8px; border-bottom: 1px solid #dadce0; text-align: left; vertical-align: top; }
        th { background: #f8f9fa; font-weight: 700; }
        .number { text-align: right; white-space: nowrap; }
        .status { font-weight: 700; }
        .printed-at { margin-top: 30px; color: #5f6368; font-size: 12px; }
        .report-footer { margin-top: 28px; padding-top: 12px; border-top: 1px solid #9aa0a6; color: #5f6368; font-size: 12px; }
        .report-footer p { margin: 2px 0; }
        .actions { display: flex; justify-content: flex-end; gap: 10px; width: calc(100% - 32px); max-width: 800px; margin: 0 auto 28px; }
        .print-button { padding: 10px 18px; border: 0; border-radius: 7px; background: #9D151D; color: #fff; cursor: pointer; font: inherit; font-weight: 600; }
        .print-button:hover { background: #410709; }
        .back-button { display: inline-block; padding: 9px 17px; border: 1px solid #9D151D; border-radius: 7px; color: #9D151D; text-decoration: none; font-weight: 600; }
        .empty { padding: 18px 8px; color: #5f6368; text-align: center; }
        @media (max-width: 680px) {
            .report-paper { width: 100%; min-height: 0; margin: 0; padding: 22px 16px; }
            .report-header { gap: 14px; }
            .report-logo { width: 66px; height: 58px; }
            .brand { font-size: 17px; }
            h1 { font-size: 22px; }
            .summary { gap: 20px; flex-wrap: wrap; }
            table { font-size: 12px; }
            th, td { padding: 7px 5px; }
            .actions { width: auto; margin: 12px 16px; }
        }
        @media print {
            @page { size: A4 portrait; margin: 14mm; }
            body { background: #fff; font-size: 10.5pt; }
            .report-paper { width: auto; max-width: none; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
            .actions { display: none; }
            .report-header { gap: 16px; padding-bottom: 12px; }
            .report-logo { width: 72px; height: 58px; }
            h1 { font-size: 21pt; }
            h2 { margin-top: 18px; break-after: avoid; page-break-after: avoid; }
            table { break-inside: auto; }
            thead { display: table-header-group; }
            tfoot { display: table-footer-group; }
            tr { break-inside: avoid; page-break-inside: avoid; }
            th, td { padding: 6px 5px; overflow-wrap: anywhere; }
            th { background: #eee !important; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .report-footer { break-inside: avoid; page-break-inside: avoid; }
        }
    </style>
</head>
<body>
<main class="report-paper">
    <header class="report-header">
        <img src="../assets/images/logo/wasibu.png" class="report-logo" alt="Logo WASIBU">
        <div class="report-heading">
            <p class="brand">WASIBU - Warung Si Bungsu</p>
            <h1>Laporan Penjualan</h1>
            <p class="subtitle">Sistem ERP F&amp;B</p>
            <p class="period">Periode: <?= htmlspecialchars($periodLabel) ?></p>
        </div>
    </header>

    <section>
        <h2>Ringkasan <?= $reportType === 'daily' ? 'Harian' : 'Bulanan' ?></h2>
        <div class="summary">
            <div>
                <span class="summary-label">Jumlah Transaksi</span>
                <span class="summary-value"><?= $reportTransactions ?></span>
            </div>
            <div>
                <span class="summary-label">Total Pendapatan</span>
                <span class="summary-value">Rp<?= number_format($reportRevenue, 0, ',', '.') ?></span>
            </div>
        </div>
        <table>
            <thead><tr><th>Tanggal</th><th class="number">Jumlah Transaksi</th><th class="number">Total Pendapatan</th></tr></thead>
            <tbody>
            <?php if ($salesRows): ?>
                <?php foreach ($salesRows as $row): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($row['sales_date'])) ?></td>
                        <td class="number"><?= (int) $row['transaction_count'] ?></td>
                        <td class="number">Rp<?= number_format((float) $row['revenue'], 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="3" class="empty">Tidak ada transaksi lunas pada periode ini.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section>
        <h2>Detail Transaksi</h2>
        <table>
            <thead><tr><th>Tanggal Transaksi</th><th>Kode Order / Invoice</th><th>Nama Customer</th><th class="number">Total Pembayaran</th><th>Status</th></tr></thead>
            <tbody>
            <?php if ($transactionDetails): ?>
                <?php foreach ($transactionDetails as $transaction): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($transaction['transaction_date'])) ?></td>
                        <td><?= htmlspecialchars($transaction['order_code']) ?></td>
                        <td><?= htmlspecialchars($transaction['customer_name']) ?></td>
                        <td class="number">Rp<?= number_format((float) $transaction['payment_total'], 0, ',', '.') ?></td>
                        <td class="status"><?= htmlspecialchars(strtoupper($transaction['payment_status'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" class="empty">Tidak ada transaksi lunas pada periode ini.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section>
        <h2>Produk Terlaris</h2>
        <table>
            <thead><tr><th>Produk</th><th class="number">Jumlah Terjual</th></tr></thead>
            <tbody>
            <?php if ($bestSellingProducts): ?>
                <?php foreach ($bestSellingProducts as $product): ?>
                    <tr>
                        <td><?= htmlspecialchars($product['product_name']) ?></td>
                        <td class="number"><?= (int) $product['quantity_sold'] ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="2" class="empty">Belum ada data penjualan.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

    <p class="printed-at"><strong>Dicetak pada:</strong> <?= htmlspecialchars($printedAt) ?></p>

    <footer class="report-footer">
        <p><strong>WASIBU ERP System</strong></p>
        <p>Laporan ini dibuat secara otomatis oleh sistem.</p>
    </footer>
</main>
<div class="actions">
    <button type="button" class="print-button" onclick="window.print()">Cetak Laporan</button>
    <a href="<?= htmlspecialchars($reportUrl, ENT_QUOTES, 'UTF-8') ?>" class="back-button">Kembali ke Laporan</a>
</div>
</body>
</html>