<?php

session_start();

require_once __DIR__ . '/auth.php';
require '../config/database.php';


/*
|--------------------------------------------------------------------------
| Harus POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: dashboard.php');
    exit;

}


$orderId = isset($_POST['order_id'])
    ? (int) $_POST['order_id']
    : 0;

$action = $_POST['action'] ?? '';


if ($orderId <= 0) {

    header('Location: dashboard.php');
    exit;

}


/*
|--------------------------------------------------------------------------
| Konfirmasi pembayaran
|--------------------------------------------------------------------------
*/

if ($action === 'confirm_payment') {

    try {
        $pdo->beginTransaction();

        $orderStmt = $pdo->prepare("SELECT id, total, payment_status FROM orders WHERE id = ? FOR UPDATE");
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch();

        if (!$order) {
            throw new RuntimeException('Pesanan tidak ditemukan.');
        }

        if ($order['payment_status'] === 'paid') {
            $pdo->commit();
            $_SESSION['admin_feedback'] = [
                'type' => 'info',
                'message' => 'Pesanan sudah lunas. Stok tidak dikurangi kembali.'
            ];
        } else {
            $paymentStmt = $pdo->prepare("
                SELECT id, method, status
                FROM payments
                WHERE order_id = ?
                ORDER BY id
                LIMIT 1
                FOR UPDATE
            ");
            $paymentStmt->execute([$orderId]);
            $payment = $paymentStmt->fetch();
            $paymentMethod = $payment['method'] ?? 'QRIS';
            $isCashPayment = $paymentMethod === 'Tunai di Kasir';

            if ($isCashPayment && $order['payment_status'] !== 'unpaid') {
                throw new RuntimeException('Status pesanan tunai tidak menunggu pembayaran.');
            }
            if (!$isCashPayment && $order['payment_status'] !== 'waiting_confirmation') {
                throw new RuntimeException('Pesanan QRIS tidak menunggu verifikasi pembayaran.');
            }

            $itemsStmt = $pdo->prepare("
                SELECT product_id, SUM(quantity) AS quantity
                FROM order_items
                WHERE order_id = ?
                GROUP BY product_id
                ORDER BY product_id
            ");
            $itemsStmt->execute([$orderId]);
            $items = $itemsStmt->fetchAll();

            if (!$items) {
                throw new RuntimeException('Pesanan tidak memiliki item produk untuk diproses.');
            }

            $quantities = [];
            foreach ($items as $item) {
                if ($item['product_id'] === null || (int) $item['quantity'] < 1) {
                    throw new RuntimeException('Item pesanan tidak memiliki data produk atau jumlah yang valid.');
                }
                $quantities[(int) $item['product_id']] = (int) $item['quantity'];
            }

            $productIds = array_keys($quantities);
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $productsStmt = $pdo->prepare("
                SELECT id, name, stock
                FROM products
                WHERE id IN ($placeholders)
                ORDER BY id
                FOR UPDATE
            ");
            $productsStmt->execute($productIds);
            $products = $productsStmt->fetchAll();

            if (count($products) !== count($quantities)) {
                throw new RuntimeException('Salah satu produk pesanan tidak ditemukan.');
            }

            foreach ($products as $product) {
                $required = $quantities[(int) $product['id']];
                if ($product['stock'] === null || (int) $product['stock'] < $required) {
                    throw new RuntimeException('Stok ' . $product['name'] . ' tidak mencukupi. Pembayaran belum dikonfirmasi.');
                }
            }

            $decrementStmt = $pdo->prepare("
                UPDATE products
                SET stock = stock - ?
                WHERE id = ? AND stock >= ?
            ");
            foreach ($quantities as $productId => $quantity) {
                $decrementStmt->execute([$quantity, $productId, $quantity]);
                if ($decrementStmt->rowCount() !== 1) {
                    throw new RuntimeException('Stok berubah saat konfirmasi. Silakan coba kembali.');
                }
            }

            if ($payment) {
                $savePayment = $pdo->prepare("
                    UPDATE payments
                    SET amount = ?, status = 'paid', paid_at = NOW()
                    WHERE id = ?
                ");
                $savePayment->execute([$order['total'], $payment['id']]);
            } else {
                $savePayment = $pdo->prepare("
                    INSERT INTO payments (order_id, method, amount, status, paid_at)
                    VALUES (?, ?, ?, 'paid', NOW())
                ");
                $savePayment->execute([$orderId, $paymentMethod, $order['total']]);
            }

            $updateOrder = $pdo->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?");
            $updateOrder->execute([$orderId]);

            $pdo->commit();
            $_SESSION['admin_feedback'] = [
                'type' => 'success',
                'message' => ($isCashPayment ? 'Pembayaran tunai' : 'Pembayaran QRIS')
                    . ' dikonfirmasi, stok diperbarui, dan pembayaran dicatat.'
            ];
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $_SESSION['admin_feedback'] = [
            'type' => 'danger',
            'message' => $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'Konfirmasi gagal. Tidak ada perubahan yang disimpan.'
        ];
    }

}


/*
|--------------------------------------------------------------------------
| Update status pesanan
|--------------------------------------------------------------------------
*/

elseif ($action === 'update_status') {


    $newStatus =
        $_POST['order_status'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Ambil pilihan ENUM langsung dari database
    |--------------------------------------------------------------------------
    */

    $statusQuery = $pdo->query("
        SHOW COLUMNS
        FROM orders
        LIKE 'order_status'
    ");

    $statusColumn =
        $statusQuery->fetch();


    $allowedStatuses = [];


    if ($statusColumn) {

        preg_match_all(
            "/'([^']+)'/",
            $statusColumn['Type'],
            $matches
        );

        $allowedStatuses =
            $matches[1] ?? [];

    }


    /*
    |--------------------------------------------------------------------------
    | Pastikan status memang valid
    |--------------------------------------------------------------------------
    */

    if (
        in_array(
            $newStatus,
            $allowedStatuses,
            true
        )
    ) {

        $stmt = $pdo->prepare("
            UPDATE orders
            SET order_status = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $newStatus,
            $orderId
        ]);

    }

}


/*
|--------------------------------------------------------------------------
| Kembali ke detail
|--------------------------------------------------------------------------
*/

header(
    'Location: order-detail.php?id='
    . $orderId
);

exit;