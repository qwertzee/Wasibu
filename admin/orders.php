<?php

session_start();

require_once __DIR__ . '/auth.php';
require_once '../config/navigation.php';
require '../config/database.php';

date_default_timezone_set('Asia/Jakarta');

$search =
    trim(
        $_GET['search'] ?? ''
    );

$orderStatus =
    trim(
        $_GET['order_status'] ?? ''
    );

$paymentStatus =
    trim(
        $_GET['payment_status'] ?? ''
    );


$sql = "
    SELECT
        orders.*,
        COALESCE(
            (SELECT payments.method FROM payments WHERE payments.order_id = orders.id ORDER BY payments.id DESC LIMIT 1),
            'QRIS'
        ) AS payment_method
    FROM orders
    WHERE 1 = 1
";

$params = [];


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            order_code LIKE ?
            OR customer_name LIKE ?
            OR phone LIKE ?
        )
    ";

    $keyword =
        '%' . $search . '%';

    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;

}

/*
|--------------------------------------------------------------------------
| Filter order
|--------------------------------------------------------------------------
*/

if ($orderStatus !== '') {

    $sql .= "
        AND order_status = ?
    ";

    $params[] =
        $orderStatus;

}


/*
|--------------------------------------------------------------------------
| Filter payment
|--------------------------------------------------------------------------
*/

if ($paymentStatus !== '') {

    $sql .= "
        AND payment_status = ?
    ";

    $params[] =
        $paymentStatus;

}


$sql .= "
    ORDER BY created_at DESC, id DESC
";


$stmt =
    $pdo->prepare($sql);

$stmt->execute($params);

$orders =
    $stmt->fetchAll();

$orderGroups = [];
foreach ($orders as $order) {
    $orderDate = (new DateTimeImmutable((string) $order['created_at'], new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
    $orderGroups[$orderDate][] = $order;
}

$today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
$yesterday = (new DateTimeImmutable('yesterday', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
$monthNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];


/*
|--------------------------------------------------------------------------
| ENUM order status
|--------------------------------------------------------------------------
*/

$orderStatusQuery =
    $pdo->query("
        SHOW COLUMNS
        FROM orders
        LIKE 'order_status'
    ");

$orderStatusColumn =
    $orderStatusQuery->fetch();


$orderStatuses = [];


if ($orderStatusColumn) {

    preg_match_all(
        "/'([^']+)'/",
        $orderStatusColumn['Type'],
        $matches
    );

    $orderStatuses =
        $matches[1] ?? [];

}


/*
|--------------------------------------------------------------------------
| ENUM payment status
|--------------------------------------------------------------------------
*/

$paymentStatusQuery =
    $pdo->query("
        SHOW COLUMNS
        FROM orders
        LIKE 'payment_status'
    ");

$paymentStatusColumn =
    $paymentStatusQuery->fetch();


$paymentStatuses = [];


if ($paymentStatusColumn) {

    preg_match_all(
        "/'([^']+)'/",
        $paymentStatusColumn['Type'],
        $matches
    );

    $paymentStatuses =
        $matches[1] ?? [];

}


function readableStatus($status)
{

    $labels = [

        'pending' =>
            'Baru',

        'confirmed' =>
            'Dikonfirmasi',

        'preparing' =>
            'Diproses',

        'ready' =>
            'Siap',

        'completed' =>
            'Selesai',

        'cancelled' =>
            'Dibatalkan',

        'waiting_confirmation' =>
            'Menunggu Verifikasi',

        'unpaid' =>
            'Menunggu Pembayaran',

        'paid' =>
            'Lunas',

        'failed' =>
            'Gagal'

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
        Semua Pesanan - WASIBU
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="../assets/css/style.css">

</head>


<body class="admin-shell admin-orders">


<?php require __DIR__ . '/navbar.php'; ?>



<main class="container py-5">


    <h2 class="fw-bold">
        Semua Pesanan
    </h2>

    <p class="text-secondary">
        Cari dan kelola seluruh pesanan customer.
    </p>



    <!-- FILTER -->

    <div
        class="card border-0 shadow-sm rounded-4 mb-4"
    >

        <div class="card-body p-4">


            <form
                method="GET"
                class="row g-3"
            >


                <div class="col-lg-5">

                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        placeholder="Kode pesanan, nama, atau nomor HP..."
                        value="<?= htmlspecialchars(
                            $search
                        ) ?>"
                    >

                </div>



                <div class="col-lg-3">

                    <select
                        name="order_status"
                        class="form-select"
                    >

                        <option value="">
                            Semua Status Pesanan
                        </option>


                        <?php foreach (
                            $orderStatuses as $status
                        ): ?>

                            <option
                                value="<?= htmlspecialchars(
                                    $status
                                ) ?>"
                                <?= $orderStatus === $status
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= htmlspecialchars(
                                    readableStatus(
                                        $status
                                    )
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>



                <div class="col-lg-3">

                    <select
                        name="payment_status"
                        class="form-select"
                    >

                        <option value="">
                            Semua Pembayaran
                        </option>


                        <?php foreach (
                            $paymentStatuses as $status
                        ): ?>

                            <option
                                value="<?= htmlspecialchars(
                                    $status
                                ) ?>"
                                <?= $paymentStatus === $status
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= htmlspecialchars(
                                    readableStatus(
                                        $status
                                    )
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>



                <div class="col-lg-1">

                    <button
                        class="btn btn-dark w-100"
                    >
                        Cari
                    </button>

                </div>


            </form>


        </div>

    </div>



    <!-- TABLE -->

    <div
        class="card border-0 shadow-sm rounded-4"
    >

        <div class="card-body p-4">


            <div
                class="d-flex justify-content-between mb-3"
            >

                <strong>
                    <?= count($orders) ?>
                    pesanan
                </strong>

                <a
                    href="orders.php"
                    class="text-decoration-none"
                >
                    Reset Filter
                </a>

            </div>


            <?php foreach ($orderGroups as $groupDate => $groupOrders): ?>
                <?php
                $groupDateObject = new DateTimeImmutable($groupDate, new DateTimeZone('Asia/Jakarta'));
                $formattedGroupDate = $groupDateObject->format('j') . ' ' . $monthNames[(int) $groupDateObject->format('n')] . ' ' . $groupDateObject->format('Y');
                if ($groupDate === $today) {
                    $groupLabel = 'Hari Ini — ' . $formattedGroupDate;
                } elseif ($groupDate === $yesterday) {
                    $groupLabel = 'Kemarin — ' . $formattedGroupDate;
                } else {
                    $groupLabel = $formattedGroupDate;
                }
                ?>
                <section class="card orders-date-group" aria-label="<?= htmlspecialchars($groupLabel, ENT_QUOTES, 'UTF-8') ?>">
                    <header class="orders-date-header d-flex justify-content-between align-items-center gap-3">
                        <h3 class="orders-date-title mb-0">
                            <i class="bi bi-calendar3" aria-hidden="true"></i>
                            <?= htmlspecialchars($groupLabel, ENT_QUOTES, 'UTF-8') ?>
                        </h3>
                        <span class="orders-date-count"><?= count($groupOrders) ?> pesanan</span>
                    </header>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle orders-date-table mb-0">
                                <colgroup>
                                    <col style="width: 15%">
                                    <col style="width: 13%">
                                    <col style="width: 10%">
                                    <col style="width: 10%">
                                    <col style="width: 10%">
                                    <col style="width: 11%">
                                    <col style="width: 12%">
                                    <col style="width: 11%">
                                    <col style="width: 8%">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>Kode</th>
                                        <th>Customer</th>
                                        <th>HP</th>
                                        <th>Total</th>
                                        <th>Metode</th>
                                        <th>Pesanan</th>
                                        <th>Pembayaran</th>
                                        <th>Waktu</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($groupOrders as $order): ?>
                                    <tr>


                            <td>

                                <strong>

                                    <?= htmlspecialchars(
                                        $order[
                                            'order_code'
                                        ]
                                    ) ?>

                                </strong>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $order[
                                        'customer_name'
                                    ]
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $order['phone']
                                ) ?>

                            </td>


                            <td>

                                Rp<?= number_format(
                                    $order['total'],
                                    0,
                                    ',',
                                    '.'
                                ) ?>

                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $order['payment_method'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    readableStatus(
                                        $order[
                                            'order_status'
                                        ]
                                    )
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    readableStatus(
                                        $order[
                                            'payment_status'
                                        ]
                                    )
                                ) ?>

                            </td>


                            <td>

                                <?= date(
                                    'd/m/Y H:i',
                                    strtotime(
                                        $order[
                                            'created_at'
                                        ]
                                    )
                                ) ?>

                            </td>


                            <td>

                                <a
                                    href="order-detail.php?<?= htmlspecialchars(http_build_query(array_merge(['id' => $order['id']], array_filter([
                                        'search' => $search,
                                        'order_status' => $orderStatus,
                                        'payment_status' => $paymentStatus
                                    ], static fn($value) => $value !== ''))), ENT_QUOTES, 'UTF-8') ?>"
                                    class="btn btn-sm btn-dark rounded-pill"
                                >
                                    Detail
                                </a>

                            </td>


                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            <?php endforeach; ?>

            <?php if (empty($orders)): ?>
                <div class="text-center text-secondary py-5">
                    Pesanan tidak ditemukan.
                </div>
            <?php endif; ?>

        </div>
    </div>

</main>


</body>

</html>