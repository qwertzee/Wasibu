<?php

session_start();

require_once __DIR__ . '/auth.php';
require_once '../config/navigation.php';
require '../config/database.php';
require_once '../config/promos.php';

if (empty($_SESSION['promo_csrf_token'])) {
    $_SESSION['promo_csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['promo_csrf_token'];
$feedback = $_SESSION['promo_feedback'] ?? null;
unset($_SESSION['promo_feedback']);

$categories = $pdo->query("
    SELECT id, title
    FROM promo_categories
    ORDER BY sort_order, title
")->fetchAll();

$products = $pdo->query("
    SELECT id, name, base_price
    FROM products
    ORDER BY name
")->fetchAll();

$form = [
    'id' => 0,
    'category_id' => '',
    'title' => '',
    'menu_text' => '',
    'promo_price' => '',
    'active_weekdays' => '',
    'description' => '',
    'terms' => '',
    'image_path' => '',
    'is_active' => 1,
    'starts_at' => '',
    'ends_at' => ''
];
$selectedProductIds = [];
$selectedProductQuantities = [];
$error = '';
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

if ($editId > 0) {
    $editStmt = $pdo->prepare("
        SELECT *
        FROM promos
        WHERE id = ?
        LIMIT 1
    ");
    $editStmt->execute([$editId]);
    $existingPromo = $editStmt->fetch();

    if (!$existingPromo) {
        header('Location: promos.php');
        exit;
    }

    $form = $existingPromo;
    $selectedProductStmt = $pdo->prepare(promoSchemaReady($pdo)
        ? "SELECT product_id, quantity FROM promo_products WHERE promo_id = ?"
        : "SELECT product_id, 1 AS quantity FROM promo_products WHERE promo_id = ?");
    $selectedProductStmt->execute([$editId]);
    foreach ($selectedProductStmt->fetchAll(PDO::FETCH_ASSOC) as $linkedProduct) {
        $linkedProductId = (int) $linkedProduct['product_id'];
        $selectedProductIds[] = $linkedProductId;
        $selectedProductQuantities[$linkedProductId] = max(1, (int) ($linkedProduct['quantity'] ?? 1));
    }
}

function promoUploadedImage(array $file): string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Gambar promo gagal diunggah.');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Ukuran gambar maksimal 5 MB.');
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];
    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);

    if (!isset($allowedTypes[$mimeType])) {
        throw new RuntimeException('Format gambar harus JPG, PNG, atau WEBP.');
    }

    $directory = __DIR__ . '/../assets/images/promos';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder penyimpanan gambar promo tidak dapat dibuat.');
    }

    $fileName = 'promo-' . bin2hex(random_bytes(12)) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $fileName)) {
        throw new RuntimeException('Gambar promo tidak dapat disimpan.');
    }

    return 'assets/images/promos/' . $fileName;
}

function promoDateValue(string $value): ?string
{
    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $dateErrors = DateTimeImmutable::getLastErrors();

    if (
        !$date
        || ($dateErrors && ($dateErrors['warning_count'] || $dateErrors['error_count']))
        || $date->format('Y-m-d') !== $value
    ) {
        throw new RuntimeException('Tanggal promo tidak valid.');
    }

    return $date->format('Y-m-d');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($postedToken) || !hash_equals($csrfToken, $postedToken)) {
        $error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.';
    } else {
        $action = $_POST['action'] ?? '';
        $promoId = isset($_POST['promo_id']) ? (int) $_POST['promo_id'] : 0;

        if ($action === 'toggle' && $promoId > 0) {
            $toggleStmt = $pdo->prepare("
                UPDATE promos
                SET is_active = IF(is_active = 1, 0, 1)
                WHERE id = ?
            ");
            $toggleStmt->execute([$promoId]);
            $_SESSION['promo_feedback'] = [
                'type' => 'success',
                'message' => $toggleStmt->rowCount() === 1
                    ? 'Status promo berhasil diperbarui.'
                    : 'Promo tidak ditemukan.'
            ];
            header('Location: promos.php');
            exit;
        }

        if ($action === 'delete' && $promoId > 0) {
            $deleteStmt = $pdo->prepare("DELETE FROM promos WHERE id = ?");
            $deleteStmt->execute([$promoId]);
            $_SESSION['promo_feedback'] = [
                'type' => $deleteStmt->rowCount() === 1 ? 'success' : 'warning',
                'message' => $deleteStmt->rowCount() === 1
                    ? 'Promo berhasil dihapus. File gambar tetap disimpan.'
                    : 'Promo tidak ditemukan.'
            ];
            header('Location: promos.php');
            exit;
        }

        if ($action === 'create' || $action === 'update') {
            $form = [
                'id' => $promoId,
                'category_id' => isset($_POST['category_id']) ? (int) $_POST['category_id'] : '',
                'title' => trim($_POST['title'] ?? ''),
                'menu_text' => trim($_POST['menu_text'] ?? ''),
                'promo_price' => trim($_POST['promo_price'] ?? ''),
                'active_weekdays' => trim($_POST['active_weekdays'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
                'terms' => trim($_POST['terms'] ?? ''),
                'image_path' => '',
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'starts_at' => trim($_POST['starts_at'] ?? ''),
                'ends_at' => trim($_POST['ends_at'] ?? '')
            ];
            $postedProductIds = $_POST['product_ids'] ?? [];
            if (!is_array($postedProductIds)) {
                $postedProductIds = [];
            }
            $selectedProductIds = array_values(array_unique(array_filter(
                array_map('intval', $postedProductIds),
                static function ($productId) {
                    return $productId > 0;
                }
            )));
            $selectedProductQuantities = [];
            $postedQuantities = $_POST['product_quantities'] ?? [];
            if (is_array($postedQuantities)) {
                foreach ($selectedProductIds as $selectedProductId) {
                    $selectedProductQuantities[$selectedProductId] = max(1, (int) ($postedQuantities[$selectedProductId] ?? 1));
                }
            }

            try {
                if ($action === 'update' && $promoId > 0) {
                    $imageStmt = $pdo->prepare("SELECT image_path FROM promos WHERE id = ?");
                    $imageStmt->execute([$promoId]);
                    $form['image_path'] = (string) $imageStmt->fetchColumn();
                    if ($form['image_path'] === '') {
                        throw new RuntimeException('Promo yang akan diedit tidak ditemukan.');
                    }
                }

                $categoryExists = $pdo->prepare("
                    SELECT id
                    FROM promo_categories
                    WHERE id = ?
                ");
                $categoryExists->execute([(int) $form['category_id']]);

                if (!$categoryExists->fetch()) {
                    throw new RuntimeException('Kategori promo wajib dipilih.');
                }
                if ($form['title'] === '') {
                    throw new RuntimeException('Nama promo wajib diisi.');
                }
                if ($form['menu_text'] === '') {
                    throw new RuntimeException('Nama atau keterangan menu wajib diisi.');
                }
                if ($form['terms'] === '') {
                    throw new RuntimeException('Syarat dan ketentuan wajib diisi.');
                }
                if (promoSchemaReady($pdo)) {
                    $promoPrice = (float) str_replace(',', '.', $form['promo_price']);
                    if ($promoPrice <= 0) {
                        throw new RuntimeException('Harga promo harus lebih besar dari 0 setelah migrasi promo diterapkan.');
                    }
                    $form['promo_price'] = number_format($promoPrice, 2, '.', '');
                    $validWeekdays = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
                    $weekdayInput = array_filter(array_map('trim', explode(',', strtolower($form['active_weekdays']))));
                    foreach ($weekdayInput as $weekday) {
                        if (!in_array($weekday, $validWeekdays, true)) {
                            throw new RuntimeException('Hari berlaku promo hanya boleh mon,tue,wed,thu,fri,sat,sun.');
                        }
                    }
                    $form['active_weekdays'] = $weekdayInput ? implode(',', $weekdayInput) : null;
                }

                $startDate = promoDateValue($form['starts_at']);
                $endDate = promoDateValue($form['ends_at']);
                if ($startDate !== null && $endDate !== null && $startDate > $endDate) {
                    throw new RuntimeException('Tanggal berakhir tidak boleh lebih awal dari tanggal mulai.');
                }

                if ($selectedProductIds) {
                    $placeholders = implode(',', array_fill(0, count($selectedProductIds), '?'));
                    $productCheck = $pdo->prepare("
                        SELECT COUNT(*)
                        FROM products
                        WHERE id IN ($placeholders)
                    ");
                    $productCheck->execute($selectedProductIds);
                    if ((int) $productCheck->fetchColumn() !== count($selectedProductIds)) {
                        throw new RuntimeException('Salah satu menu yang dipilih tidak ditemukan.');
                    }
                }

                if (
                    isset($_FILES['image'])
                    && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE
                ) {
                    $form['image_path'] = promoUploadedImage($_FILES['image']);
                } elseif ($action === 'create' && $form['image_path'] === '') {
                    throw new RuntimeException('Gambar promo wajib diunggah.');
                }

                if ($form['image_path'] === '') {
                    throw new RuntimeException('Gambar promo wajib tersedia.');
                }

                $pdo->beginTransaction();
                if ($action === 'create') {
                    if (promoSchemaReady($pdo)) {
                        $saveStmt = $pdo->prepare("
                            INSERT INTO promos
                                (legacy_key, category_id, title, menu_text, promo_price, active_weekdays, description, terms, image_path, is_active, starts_at, ends_at, sort_order)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
                        ");
                        $saveStmt->execute([
                            'admin-' . bin2hex(random_bytes(16)),
                            (int) $form['category_id'],
                            $form['title'],
                            $form['menu_text'],
                            $form['promo_price'],
                            $form['active_weekdays'],
                            $form['description'] !== '' ? $form['description'] : null,
                            $form['terms'],
                            $form['image_path'],
                            (int) $form['is_active'],
                            $startDate,
                            $endDate
                        ]);
                    } else {
                        $saveStmt = $pdo->prepare("
                            INSERT INTO promos
                                (legacy_key, category_id, title, menu_text, description, terms, image_path, is_active, starts_at, ends_at, sort_order)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
                        ");
                        $saveStmt->execute([
                            'admin-' . bin2hex(random_bytes(16)),
                            (int) $form['category_id'],
                            $form['title'],
                            $form['menu_text'],
                            $form['description'] !== '' ? $form['description'] : null,
                            $form['terms'],
                            $form['image_path'],
                            (int) $form['is_active'],
                            $startDate,
                            $endDate
                        ]);
                    }
                    $promoId = (int) $pdo->lastInsertId();
                } else {
                    if (promoSchemaReady($pdo)) {
                        $saveStmt = $pdo->prepare("
                            UPDATE promos
                            SET category_id = ?, title = ?, menu_text = ?, promo_price = ?, active_weekdays = ?,
                                description = ?, terms = ?, image_path = ?, is_active = ?, starts_at = ?, ends_at = ?
                            WHERE id = ?
                        ");
                        $saveStmt->execute([
                            (int) $form['category_id'], $form['title'], $form['menu_text'],
                            $form['promo_price'], $form['active_weekdays'],
                            $form['description'] !== '' ? $form['description'] : null,
                            $form['terms'], $form['image_path'], (int) $form['is_active'],
                            $startDate, $endDate, $promoId
                        ]);
                    } else {
                        $saveStmt = $pdo->prepare("
                            UPDATE promos
                            SET category_id = ?, title = ?, menu_text = ?, description = ?, terms = ?,
                                image_path = ?, is_active = ?, starts_at = ?, ends_at = ?
                            WHERE id = ?
                        ");
                        $saveStmt->execute([
                            (int) $form['category_id'], $form['title'], $form['menu_text'],
                            $form['description'] !== '' ? $form['description'] : null,
                            $form['terms'], $form['image_path'], (int) $form['is_active'],
                            $startDate, $endDate, $promoId
                        ]);
                    }
                }

                $pdo->prepare("DELETE FROM promo_products WHERE promo_id = ?")->execute([$promoId]);
                if ($selectedProductIds) {
                    if (promoSchemaReady($pdo)) {
                        $linkStmt = $pdo->prepare("
                            INSERT INTO promo_products (promo_id, product_id, quantity)
                            VALUES (?, ?, ?)
                        ");
                        foreach ($selectedProductIds as $productId) {
                            $linkStmt->execute([$promoId, $productId, $selectedProductQuantities[$productId] ?? 1]);
                        }
                    } else {
                        $linkStmt = $pdo->prepare("
                            INSERT INTO promo_products (promo_id, product_id)
                            VALUES (?, ?)
                        ");
                        foreach ($selectedProductIds as $productId) {
                            $linkStmt->execute([$promoId, $productId]);
                        }
                    }
                }

                $pdo->commit();
                $_SESSION['promo_feedback'] = [
                    'type' => 'success',
                    'message' => $action === 'create'
                        ? 'Promo berhasil ditambahkan.'
                        : 'Perubahan promo berhasil disimpan.'
                ];
                header('Location: promos.php');
                exit;
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($exception instanceof RuntimeException) {
                    $error = $exception->getMessage();
                } else {
                    error_log('Promo save failed: ' . $exception->getMessage());
                    $error = 'Promo gagal disimpan. Periksa data dan coba kembali.';
                }
            }
        } else {
            $error = 'Aksi promo tidak valid.';
        }
    }
}

$promoRows = $pdo->query("
    SELECT
        p.*,
        c.title AS category_title,
        (
            SELECT COUNT(*)
            FROM promo_products pp
            WHERE pp.promo_id = p.id
        ) AS linked_product_count
    FROM promos p
    INNER JOIN promo_categories c ON c.id = p.category_id
    ORDER BY c.sort_order, p.sort_order, p.id
")->fetchAll();

function promoAdminStatus(array $promo): string
{
    if (!(int) $promo['is_active']) {
        return 'Nonaktif';
    }
    if ($promo['starts_at'] !== null && $promo['starts_at'] > date('Y-m-d')) {
        return 'Belum dimulai';
    }
    if ($promo['ends_at'] !== null && $promo['ends_at'] < date('Y-m-d')) {
        return 'Kedaluwarsa';
    }

    return 'Aktif';
}

$isEditing = (int) $form['id'] > 0;

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Promo - WASIBU Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-shell admin-promos">
<?php require __DIR__ . '/navbar.php'; ?>

<main class="container py-5">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h2 fw-bold mb-1">Manajemen Promo</h1>
            <p class="text-secondary mb-0">Kelola Promo Harian dan Paket Hemat WASIBU.</p>
        </div>
        <a class="btn btn-dark rounded-pill px-4" href="#promo-form">Tambah Promo</a>
    </div>

    <?php if ($feedback): ?>
        <div class="alert alert-<?= htmlspecialchars($feedback['type'], ENT_QUOTES, 'UTF-8') ?>" role="alert">
            <?= htmlspecialchars($feedback['message'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <section class="card admin-order-card shadow-sm mb-4">
        <div class="card-body p-3 p-lg-4">
            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Promo</th>
                            <th>Kategori</th>
                            <th>Status</th>
                            <th>Periode</th>
                            <th>Menu terkait</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($promoRows as $promo): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <img
                                        src="../<?= htmlspecialchars($promo['image_path'], ENT_QUOTES, 'UTF-8') ?>"
                                        alt=""
                                        width="76"
                                        height="52"
                                        class="rounded object-fit-cover"
                                    >
                                    <div>
                                        <strong><?= htmlspecialchars($promo['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <div class="small text-secondary"><?= htmlspecialchars($promo['menu_text'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($promo['category_title'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="badge <?= promoAdminStatus($promo) === 'Aktif' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                    <?= htmlspecialchars(promoAdminStatus($promo), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="small">
                                <?= $promo['starts_at'] ? htmlspecialchars($promo['starts_at'], ENT_QUOTES, 'UTF-8') : 'Tanpa tanggal mulai' ?>
                                <br>
                                <?= $promo['ends_at'] ? htmlspecialchars($promo['ends_at'], ENT_QUOTES, 'UTF-8') : 'Tanpa tanggal akhir' ?>
                            </td>
                            <td><?= (int) $promo['linked_product_count'] ?></td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <a class="btn btn-sm btn-outline-dark rounded-pill" href="promos.php?edit=<?= (int) $promo['id'] ?>">Edit</a>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="promo_id" value="<?= (int) $promo['id'] ?>">
                                        <input type="hidden" name="action" value="toggle">
                                        <button class="btn btn-sm btn-outline-secondary rounded-pill" type="submit">
                                            <?= (int) $promo['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Hapus promo ini? File gambar tidak akan dihapus.');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="promo_id" value="<?= (int) $promo['id'] ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button class="btn btn-sm btn-outline-danger rounded-pill" type="submit">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$promoRows): ?>
                        <tr><td colspan="6" class="text-center text-secondary py-4">Belum ada data promo.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="card admin-order-card shadow-sm" id="promo-form">
        <div class="card-body p-4">
            <h2 class="h4 fw-bold mb-4"><?= $isEditing ? 'Edit Promo' : 'Tambah Promo' ?></h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="promo_id" value="<?= (int) $form['id'] ?>">
                <input type="hidden" name="action" value="<?= $isEditing ? 'update' : 'create' ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="category_id" class="form-label">Kategori</label>
                        <select id="category_id" name="category_id" class="form-select" required>
                            <option value="">Pilih kategori</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['id'] ?>" <?= (int) $form['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['title'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="title" class="form-label">Nama Promo</label>
                        <input id="title" name="title" class="form-control" maxlength="150" value="<?= htmlspecialchars($form['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="col-12">
                        <label for="menu_text" class="form-label">Nama/keterangan menu</label>
                        <input id="menu_text" name="menu_text" class="form-control" maxlength="255" value="<?= htmlspecialchars($form['menu_text'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <?php if (promoSchemaReady($pdo)): ?>
                        <div class="col-md-6">
                            <label for="promo_price" class="form-label">Harga Promo</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input id="promo_price" type="number" name="promo_price" class="form-control" min="1" step="1" value="<?= htmlspecialchars($form['promo_price'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="active_weekdays" class="form-label">Hari berlaku (opsional)</label>
                            <input id="active_weekdays" name="active_weekdays" class="form-control" maxlength="20" placeholder="mon,tue,wed" value="<?= htmlspecialchars($form['active_weekdays'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <div class="form-text">Kosongkan untuk berlaku setiap hari. Gunakan kode mon,tue,wed,thu,fri,sat,sun.</div>
                        </div>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-warning mb-0">Konfigurasi pemesanan promo belum aktif. Jalankan migration SQL secara manual sebelum mengatur harga dan jumlah paket.</div>
                        </div>
                    <?php endif; ?>
                    <div class="col-12">
                        <label class="form-label">Hubungkan ke menu katalog dan jumlah isi paket</label>
                        <div class="border rounded p-3">
                            <?php foreach ($products as $product): ?>
                                <?php $productId = (int) $product['id']; ?>
                                <div class="d-flex align-items-center gap-3 py-1">
                                    <div class="form-check flex-grow-1 mb-0">
                                        <input class="form-check-input" type="checkbox" id="product_<?= $productId ?>" name="product_ids[]" value="<?= $productId ?>" <?= in_array($productId, $selectedProductIds, true) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="product_<?= $productId ?>">
                                            <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>
                                            <?= $product['base_price'] !== null ? ' — Rp' . number_format((float) $product['base_price'], 0, ',', '.') : '' ?>
                                        </label>
                                    </div>
                                    <input type="number" name="product_quantities[<?= $productId ?>]" class="form-control" style="width:90px" min="1" value="<?= (int) ($selectedProductQuantities[$productId] ?? 1) ?>" aria-label="Jumlah <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text">Centang hanya menu yang benar-benar termasuk paket, lalu isi jumlahnya.</div>
                    </div>
                    <div class="col-12">
                        <label for="description" class="form-label">Deskripsi</label>
                        <textarea id="description" name="description" class="form-control" rows="3"><?= htmlspecialchars($form['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label for="terms" class="form-label">Syarat dan Ketentuan (satu syarat per baris)</label>
                        <textarea id="terms" name="terms" class="form-control" rows="3" required><?= htmlspecialchars($form['terms'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label for="starts_at" class="form-label">Tanggal Mulai (opsional)</label>
                        <input id="starts_at" type="date" name="starts_at" class="form-control" value="<?= htmlspecialchars($form['starts_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="ends_at" class="form-label">Tanggal Berakhir (opsional)</label>
                        <input id="ends_at" type="date" name="ends_at" class="form-control" value="<?= htmlspecialchars($form['ends_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-8">
                        <label for="image" class="form-label">Gambar Promo <?= $isEditing ? '(opsional untuk mengganti)' : '' ?></label>
                        <input id="image" type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp" <?= $isEditing ? '' : 'required' ?>>
                        <div class="form-text">JPG, PNG, atau WEBP; maksimal 5 MB. Gambar lama tidak dihapus saat promo diedit atau dihapus.</div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input id="is_active" type="checkbox" name="is_active" class="form-check-input" value="1" <?= (int) $form['is_active'] === 1 ? 'checked' : '' ?>>
                            <label for="is_active" class="form-check-label">Promo aktif</label>
                        </div>
                    </div>
                    <?php if (!empty($form['image_path'])): ?>
                        <div class="col-12">
                            <small class="text-secondary d-block mb-2">Gambar saat ini</small>
                            <img src="../<?= htmlspecialchars($form['image_path'], ENT_QUOTES, 'UTF-8') ?>" alt="" style="max-width: 220px; max-height: 140px; object-fit: cover;">
                        </div>
                    <?php endif; ?>
                    <div class="col-12 d-flex flex-wrap gap-2 pt-2">
                        <button type="submit" class="btn btn-dark rounded-pill px-4"><?= $isEditing ? 'Simpan Perubahan' : 'Tambah Promo' ?></button>
                        <?php if ($isEditing): ?>
                            <a href="promos.php" class="btn btn-outline-dark rounded-pill px-4">Batal</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </section>
</main>
</body>
</html>
