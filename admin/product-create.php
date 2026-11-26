<?php

session_start();

require_once __DIR__ . '/auth.php';
require_once '../config/navigation.php';
require '../config/database.php';


/*
|--------------------------------------------------------------------------
| Ambil kategori
|--------------------------------------------------------------------------
*/

$categoryQuery = $pdo->query("
    SELECT id, name
    FROM categories
    ORDER BY sort_order ASC, name ASC
");

$categories = $categoryQuery->fetchAll();


$error = '';


/*
|--------------------------------------------------------------------------
| Fungsi membuat slug
|--------------------------------------------------------------------------
*/

function createSlug($text)
{

    $text = strtolower(trim($text));

    $text = preg_replace(
        '/[^a-z0-9]+/',
        '-',
        $text
    );

    return trim($text, '-');

}



/*
|--------------------------------------------------------------------------
| Proses tambah menu
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    $name = trim(
        $_POST['name'] ?? ''
    );


    $categoryId = isset($_POST['category_id'])
        ? (int) $_POST['category_id']
        : 0;


    $description = trim(
        $_POST['description'] ?? ''
    );


    $basePrice = isset($_POST['base_price'])
        ? (float) $_POST['base_price']
        : 0;

        $stock = $_POST['stock'];

    $compareAtPrice =
        isset($_POST['compare_at_price'])
        &&
        $_POST['compare_at_price'] !== ''
            ? (float) $_POST['compare_at_price']
            : null;


    $isAvailable =
        isset($_POST['is_available'])
            ? 1
            : 0;


    $isFeatured =
        isset($_POST['is_featured'])
            ? 1
            : 0;


    /*
    |--------------------------------------------------------------------------
    | Validasi
    |--------------------------------------------------------------------------
    */

    if ($name === '') {

        $error = 'Nama menu wajib diisi.';

    } elseif ($categoryId <= 0) {

        $error = 'Kategori wajib dipilih.';

    } elseif ($basePrice <= 0) {

        $error = 'Harga menu harus lebih dari 0.';

    } else {


        /*
        |--------------------------------------------------------------------------
        | Buat slug unik
        |--------------------------------------------------------------------------
        */

        $baseSlug = createSlug($name);

        $slug = $baseSlug;

        $number = 1;


        while (true) {

            $slugCheck = $pdo->prepare("
                SELECT id
                FROM products
                WHERE slug = ?
                LIMIT 1
            ");

            $slugCheck->execute([
                $slug
            ]);


            if (!$slugCheck->fetch()) {
                break;
            }


            $number++;

            $slug =
                $baseSlug
                . '-'
                . $number;

        }


        /*
        |--------------------------------------------------------------------------
        | Upload gambar
        |--------------------------------------------------------------------------
        */

        $imageName = null;


        if (
            isset($_FILES['image'])
            &&
            $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE
        ) {


            if (
                $_FILES['image']['error']
                !== UPLOAD_ERR_OK
            ) {

                $error =
                    'Terjadi kesalahan saat upload gambar.';

            } elseif (
                $_FILES['image']['size']
                > 5 * 1024 * 1024
            ) {

                $error =
                    'Ukuran gambar maksimal 5 MB.';

            } else {


                $allowedTypes = [

                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp'

                ];


                $finfo =
                    new finfo(FILEINFO_MIME_TYPE);


                $mimeType =
                    $finfo->file(
                        $_FILES['image']['tmp_name']
                    );


                if (
                    !isset(
                        $allowedTypes[$mimeType]
                    )
                ) {

                    $error =
                        'Format gambar harus JPG, PNG, atau WEBP.';

                } else {


                    $extension =
                        $allowedTypes[$mimeType];


                    $imageName =
                        $slug
                        . '-'
                        . time()
                        . '.'
                        . $extension;


                    $uploadDirectory =
                        __DIR__
                        . '/../assets/images/products/';


                    /*
                    |--------------------------------------------------------------------------
                    | Buat folder kalau belum ada
                    |--------------------------------------------------------------------------
                    */

                    if (
                        !is_dir($uploadDirectory)
                    ) {

                        mkdir(
                            $uploadDirectory,
                            0777,
                            true
                        );

                    }


                    $destination =
                        $uploadDirectory
                        . $imageName;


                    if (
                        !move_uploaded_file(
                            $_FILES['image']['tmp_name'],
                            $destination
                        )
                    ) {

                        $error =
                            'Gambar gagal disimpan.';

                    }

                }

            }

        }


        /*
        |--------------------------------------------------------------------------
        | Simpan ke database
        |--------------------------------------------------------------------------
        */

        if ($error === '') {


            $stmt = $pdo->prepare("
                INSERT INTO products (
                    category_id,
                    name,
                    slug,
                    description,
                    base_price,
                    compare_at_price,
                    image,
                    stock,
                    is_available,
                    is_featured,
                    needs_confirmation
                )
               VALUES (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                0
            )
            ");



                    $stmt->execute([

            $categoryId,

            $name,

            $slug,

            $description,

            $basePrice,

            $compareAtPrice,

            $imageName,

            $stock,

            $isAvailable,

            $isFeatured

        ]);

            header(
                'Location: products.php?created=1'
            );

            exit;

        }

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
        Tambah Menu - WASIBU Admin
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

        .product-form-card {
            border: 0;
            border-radius: 22px;
        }

        .product-form-card .form-control,
        .product-form-card .form-select {
            border-radius: 14px;
        }

        .product-form-card .form-control:focus,
        .product-form-card .form-select:focus {
            border-color: #212529;
            box-shadow: none;
        }

    </style>

</head>


<body class="admin-shell admin-product-form">


<?php require __DIR__ . '/navbar.php'; ?>



<!-- ============================================================= -->
<!-- CONTENT -->
<!-- ============================================================= -->

<main class="container py-5">


    <div class="row justify-content-center">


        <div class="col-lg-8">


            <a
                href="products.php"
                class="text-decoration-none text-secondary d-inline-block mb-4"
            >
                ← Kembali ke Kelola Menu
            </a>


            <div class="mb-4">

                <h2 class="fw-bold mb-1">
                    Tambah Menu
                </h2>

                <p class="text-secondary mb-0">
                    Tambahkan makanan atau minuman baru.
                </p>

            </div>



            <?php if ($error !== ''): ?>

                <div class="alert alert-danger">

                    <?= htmlspecialchars(
                        $error
                    ) ?>

                </div>

            <?php endif; ?>



            <div
                class="card product-form-card shadow-sm"
            >


                <div class="card-body p-4 p-md-5">


                    <form
                        method="POST"
                        enctype="multipart/form-data"
                    >


                        <!-- NAMA MENU -->

                        <div class="mb-4">

                            <label
                                for="name"
                                class="form-label fw-semibold"
                            >
                                Nama Menu
                            </label>


                            <input
                                type="text"
                                id="name"
                                name="name"
                                class="form-control form-control-lg"
                                placeholder="Contoh: Ayam Geprek"
                                value="<?= htmlspecialchars(
                                    $_POST['name'] ?? ''
                                ) ?>"
                                required
                            >

                        </div>



                        <!-- KATEGORI -->

                        <div class="mb-4">

                            <label
                                for="category_id"
                                class="form-label fw-semibold"
                            >
                                Kategori
                            </label>


                            <select
                                id="category_id"
                                name="category_id"
                                class="form-select form-select-lg"
                                required
                            >

                                <option value="">
                                    Pilih kategori
                                </option>


                                <?php foreach (
                                    $categories as $category
                                ): ?>


                                    <option
                                        value="<?= $category['id'] ?>"
                                        <?= (
                                            ($_POST['category_id'] ?? '')
                                            == $category['id']
                                        )
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >

                                        <?= htmlspecialchars(
                                            $category['name']
                                        ) ?>

                                    </option>


                                <?php endforeach; ?>


                            </select>

                        </div>



                        <!-- DESKRIPSI -->

                        <div class="mb-4">

                            <label
                                for="description"
                                class="form-label fw-semibold"
                            >
                                Deskripsi Menu
                            </label>


                            <textarea
                                id="description"
                                name="description"
                                class="form-control"
                                rows="4"
                                placeholder="Jelaskan menu..."
                            ><?= htmlspecialchars(
                                $_POST['description'] ?? ''
                            ) ?></textarea>

                        </div>



                        <div class="row">


                            <!-- HARGA -->

                            <div class="col-md-6 mb-4">

                                <label
                                    for="base_price"
                                    class="form-label fw-semibold"
                                >
                                    Harga
                                </label>


                                <div class="input-group">

                                    <span
                                        class="input-group-text"
                                    >
                                        Rp
                                    </span>


                                    <input
                                        type="number"
                                        id="base_price"
                                        name="base_price"
                                        class="form-control"
                                        min="0"
                                        step="500"
                                        placeholder="15000"
                                        value="<?= htmlspecialchars(
                                            $_POST['base_price'] ?? ''
                                        ) ?>"
                                        required
                                    >

                                </div>

                            </div>

<!-- STOK -->

<div class="col-md-6 mb-4">

    <label
        class="form-label fw-semibold"
    >
        Stok Produk
    </label>


    <input
        type="number"
        name="stock"
        class="form-control"
        value="50"
        min="0"
        required
    >

</div>
                            <!-- HARGA SEBELUM DISKON -->

                            <div class="col-md-6 mb-4">

                                <label
                                    for="compare_at_price"
                                    class="form-label fw-semibold"
                                >
                                    Harga Sebelum Diskon
                                </label>


                                <div class="input-group">

                                    <span
                                        class="input-group-text"
                                    >
                                        Rp
                                    </span>


                                    <input
                                        type="number"
                                        id="compare_at_price"
                                        name="compare_at_price"
                                        class="form-control"
                                        min="0"
                                        step="500"
                                        placeholder="Opsional"
                                        value="<?= htmlspecialchars(
                                            $_POST[
                                                'compare_at_price'
                                            ] ?? ''
                                        ) ?>"
                                    >

                                </div>


                                <small class="text-secondary">
                                    Kosongkan jika tidak ada diskon.
                                </small>

                            </div>


                        </div>



                        <!-- FOTO -->

                        <div class="mb-4">

                            <label
                                for="image"
                                class="form-label fw-semibold"
                            >
                                Foto Menu
                            </label>


                            <input
                                type="file"
                                id="image"
                                name="image"
                                class="form-control"
                                accept=".jpg,.jpeg,.png,.webp"
                            >


                            <small class="text-secondary">
                                JPG, PNG, atau WEBP. Maksimal 5 MB.
                            </small>

                        </div>



                        <hr class="my-4">



                        <!-- AKTIF -->

                        <div class="form-check form-switch mb-3">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="is_available"
                                name="is_available"
                                value="1"
                                <?= !isset($_POST['name'])
                                    || isset($_POST['is_available'])
                                    ? 'checked'
                                    : ''
                                ?>
                            >


                            <label
                                class="form-check-label"
                                for="is_available"
                            >
                                Menu tersedia / aktif
                            </label>

                        </div>



                        <!-- FAVORIT -->

                        <div class="form-check form-switch mb-4">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="is_featured"
                                name="is_featured"
                                value="1"
                                <?= isset($_POST['is_featured'])
                                    ? 'checked'
                                    : ''
                                ?>
                            >


                            <label
                                class="form-check-label"
                                for="is_featured"
                            >
                                Jadikan menu favorit
                            </label>

                        </div>



                        <button
                            type="submit"
                            class="btn btn-dark w-100 rounded-pill py-3"
                        >
                            Simpan Menu
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