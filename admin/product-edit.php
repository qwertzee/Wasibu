<?php

session_start();

require_once __DIR__ . '/auth.php';
require_once '../config/navigation.php';
require '../config/database.php';


/*
|--------------------------------------------------------------------------
| Ambil ID produk
|--------------------------------------------------------------------------
*/

$productId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($productId <= 0) {
    header('Location: products.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Ambil produk
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM products
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$productId]);

$product = $stmt->fetch();

if (!$product) {
    header('Location: products.php');
    exit;
}


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
| Fungsi slug
|--------------------------------------------------------------------------
*/

function createProductSlug($text)
{
    $text = strtolower(trim($text));

    $text = preg_replace(
        '/[^a-z0-9]+/',
        '-',
        $text
    );

    $text = trim($text, '-');

    if ($text === '') {
        $text = 'menu';
    }

    return $text;
}


/*
|--------------------------------------------------------------------------
| Proses edit
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');

    $categoryId = isset($_POST['category_id'])
        ? (int) $_POST['category_id']
        : 0;

    $description = trim(
        $_POST['description'] ?? ''
    );

    $basePrice = isset($_POST['base_price'])
        ? (float) $_POST['base_price']
        : 0;


    $stock = isset($_POST['stock'])
    ? (int) $_POST['stock']
    : 0;

    $compareAtPrice =
        isset($_POST['compare_at_price'])
        && $_POST['compare_at_price'] !== ''
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


    if ($name === '') {

        $error = 'Nama menu wajib diisi.';

    } elseif ($categoryId <= 0) {

        $error = 'Kategori wajib dipilih.';

    } elseif ($basePrice <= 0) {

        $error = 'Harga harus lebih dari 0.';

    }


    /*
    |--------------------------------------------------------------------------
    | Slug
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $baseSlug = createProductSlug($name);

        $slug = $baseSlug;

        $number = 1;


        while (true) {

            $slugStmt = $pdo->prepare("
                SELECT id
                FROM products
                WHERE slug = ?
                  AND id != ?
                LIMIT 1
            ");

            $slugStmt->execute([
                $slug,
                $productId
            ]);


            if (!$slugStmt->fetch()) {
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
        | Foto lama
        |--------------------------------------------------------------------------
        */

        $oldImage = $product['image'];

        $imageName = $oldImage;

        $deleteOldImage = false;


        /*
        |--------------------------------------------------------------------------
        | Hapus foto
        |--------------------------------------------------------------------------
        */

        if (isset($_POST['remove_image'])) {

            $imageName = null;

            $deleteOldImage = true;

        }


        /*
        |--------------------------------------------------------------------------
        | Upload foto baru
        |--------------------------------------------------------------------------
        */

        if (
            isset($_FILES['image'])
            &&
            $_FILES['image']['error']
            !== UPLOAD_ERR_NO_FILE
        ) {


            if (
                $_FILES['image']['error']
                !== UPLOAD_ERR_OK
            ) {

                $error =
                    'Terjadi kesalahan saat upload foto.';

            } elseif (
                $_FILES['image']['size']
                > 5 * 1024 * 1024
            ) {

                $error =
                    'Ukuran foto maksimal 5 MB.';

            } else {


                $allowedTypes = [

                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
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
                        'Foto harus JPG, PNG, atau WEBP.';

                } else {


                    $extension =
                        $allowedTypes[$mimeType];


                    $newImageName =
                        $slug
                        . '-'
                        . time()
                        . '.'
                        . $extension;


                    $uploadDirectory =
                        __DIR__
                        . '/../assets/images/products/';


                    if (!is_dir($uploadDirectory)) {

                        mkdir(
                            $uploadDirectory,
                            0777,
                            true
                        );

                    }


                    if (
                        move_uploaded_file(
                            $_FILES['image']['tmp_name'],
                            $uploadDirectory
                            . $newImageName
                        )
                    ) {

                        $imageName =
                            $newImageName;

                        $deleteOldImage =
                            true;

                    } else {

                        $error =
                            'Foto gagal disimpan.';

                    }

                }

            }

        }


        /*
        |--------------------------------------------------------------------------
        | Update database
        |--------------------------------------------------------------------------
        */

        if ($error === '') {


            $update = $pdo->prepare("
                UPDATE products
                SET
                    category_id = ?,
                    name = ?,
                    slug = ?,
                    description = ?,
                    base_price = ?,
                    compare_at_price = ?,
                    image = ?,
                    stock = ?,
                    is_available = ?,
                    is_featured = ?
                WHERE id = ?
            ");


            $update->execute([

                $categoryId,
                $name,
                $slug,
                $description,
                $basePrice,
                $compareAtPrice,
                $imageName,
                $stock,
                $isAvailable,
                $isFeatured,
                $productId

            ]);


            /*
            |--------------------------------------------------------------------------
            | Hapus foto lama kalau diganti/dihapus
            |--------------------------------------------------------------------------
            */

            if (
                $deleteOldImage
                &&
                !empty($oldImage)
                &&
                $oldImage !== $imageName
            ) {

                $oldPath =
                    __DIR__
                    . '/../assets/images/products/'
                    . $oldImage;


                if (is_file($oldPath)) {
                    unlink($oldPath);
                }

            }


            header(
                'Location: products.php?updated=1'
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

    <title>Edit Menu - WASIBU Admin</title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="../assets/css/logo.css">
    <link rel="stylesheet" href="../assets/css/style.css">

</head>


<body class="admin-shell admin-product-form">


<?php require __DIR__ . '/navbar.php'; ?>



<main class="container py-5">


    <div class="row justify-content-center">

        <div class="col-lg-8">


            <a
                href="products.php"
                class="text-decoration-none text-secondary d-inline-block mb-4"
            >
                ← Kembali
            </a>


            <h2 class="fw-bold">
                Edit Menu
            </h2>

            <p class="text-secondary mb-4">
                Ubah informasi menu WASIBU.
            </p>


            <?php if ($error !== ''): ?>

                <div class="alert alert-danger">

                    <?= htmlspecialchars(
                        $error
                    ) ?>

                </div>

            <?php endif; ?>


            <div
                class="card border-0 shadow-sm rounded-4"
            >

                <div class="card-body p-4 p-md-5">


                    <form
                        method="POST"
                        enctype="multipart/form-data"
                    >


                        <div class="mb-4">

                            <label
                                class="form-label fw-semibold"
                            >
                                Nama Menu
                            </label>

                            <input
                                type="text"
                                name="name"
                                class="form-control form-control-lg"
                                value="<?= htmlspecialchars(
                                    $_POST['name']
                                    ?? $product['name']
                                ) ?>"
                                required
                            >

                        </div>



                        <div class="mb-4">

                            <label
                                class="form-label fw-semibold"
                            >
                                Kategori
                            </label>


                            <select
                                name="category_id"
                                class="form-select form-select-lg"
                                required
                            >


                                <?php foreach (
                                    $categories as $category
                                ): ?>


                                    <?php

                                    $selectedCategory =
                                        $_POST['category_id']
                                        ?? $product['category_id'];

                                    ?>


                                    <option
                                        value="<?= $category['id'] ?>"
                                        <?= (
                                            $selectedCategory
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



                        <div class="mb-4">

                            <label
                                class="form-label fw-semibold"
                            >
                                Deskripsi
                            </label>

                            <textarea
                                name="description"
                                class="form-control"
                                rows="4"
                            ><?= htmlspecialchars(
                                $_POST['description']
                                ?? $product['description']
                            ) ?></textarea>

                        </div>



                        <div class="row">


                            <div class="col-md-6 mb-4">

                                <label
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
                                        name="base_price"
                                        class="form-control"
                                        value="<?= htmlspecialchars(
                                            $_POST['base_price']
                                            ?? $product['base_price']
                                        ) ?>"
                                        min="0"
                                        required
                                    >

                                </div>

                            </div>

                                                    <div class="mb-3">

                            <label class="form-label fw-semibold">
                                Stok Produk
                            </label>

                            <input
                                type="number"
                                name="stock"
                                class="form-control"
                                min="0"
                                value="<?= htmlspecialchars($product['stock']) ?>"
                                required
                            >

                        </div>

                            <div class="col-md-6 mb-4">

                                <label
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
                                        name="compare_at_price"
                                        class="form-control"
                                        value="<?= htmlspecialchars(
                                            $_POST['compare_at_price']
                                            ?? $product[
                                                'compare_at_price'
                                            ]
                                            ?? ''
                                        ) ?>"
                                        min="0"
                                    >

                                </div>

                            </div>


                        </div>



                        <div class="mb-4">

                            <label
                                class="form-label fw-semibold"
                            >
                                Foto Menu
                            </label>


                            <?php if (
                                !empty($product['image'])
                            ): ?>

                                <div class="mb-3">

                                    <img
                                        src="../assets/images/products/<?= htmlspecialchars(
                                            $product['image']
                                        ) ?>"
                                        alt=""
                                        style="
                                            width: 140px;
                                            height: 140px;
                                            object-fit: cover;
                                            border-radius: 16px;
                                        "
                                    >

                                </div>


                                <div
                                    class="form-check mb-3"
                                >

                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        id="remove_image"
                                        name="remove_image"
                                        value="1"
                                    >

                                    <label
                                        for="remove_image"
                                        class="form-check-label text-danger"
                                    >
                                        Hapus foto sekarang
                                    </label>

                                </div>

                            <?php endif; ?>


                            <input
                                type="file"
                                name="image"
                                class="form-control"
                                accept=".jpg,.jpeg,.png,.webp"
                            >

                            <small class="text-secondary">
                                Kosongkan jika tidak ingin mengganti foto.
                            </small>

                        </div>



                        <hr>



                        <div
                            class="form-check form-switch mb-3"
                        >

                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="is_available"
                                name="is_available"
                                value="1"
                                <?= (
                                    isset($_POST['name'])
                                        ? isset(
                                            $_POST[
                                                'is_available'
                                            ]
                                        )
                                        : (int)
                                            $product[
                                                'is_available'
                                            ] === 1
                                )
                                    ? 'checked'
                                    : ''
                                ?>
                            >

                            <label
                                class="form-check-label"
                                for="is_available"
                            >
                                Menu aktif / tersedia
                            </label>

                        </div>



                        <div
                            class="form-check form-switch mb-4"
                        >

                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="is_featured"
                                name="is_featured"
                                value="1"
                                <?= (
                                    isset($_POST['name'])
                                        ? isset(
                                            $_POST[
                                                'is_featured'
                                            ]
                                        )
                                        : (int)
                                            $product[
                                                'is_featured'
                                            ] === 1
                                )
                                    ? 'checked'
                                    : ''
                                ?>
                            >

                            <label
                                class="form-check-label"
                                for="is_featured"
                            >
                                Menu favorit
                            </label>

                        </div>



                        <button
                            type="submit"
                            class="btn btn-dark w-100 rounded-pill py-3"
                        >
                            Simpan Perubahan
                        </button>


                    </form>


                </div>

            </div>

        </div>

    </div>

</main>


</body>

</html>