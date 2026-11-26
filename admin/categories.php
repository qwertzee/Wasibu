<?php

session_start();

require_once __DIR__ . '/auth.php';
require_once '../config/navigation.php';
require '../config/database.php';

$error = '';
$success = '';


/*
|--------------------------------------------------------------------------
| Deteksi kolom categories
|--------------------------------------------------------------------------
*/

$columnQuery = $pdo->query("
    SHOW COLUMNS
    FROM categories
");

$categoryColumns = $columnQuery->fetchAll();

$columnNames = [];

foreach ($categoryColumns as $column) {
    $columnNames[] = $column['Field'];
}


$hasSlug =
    in_array('slug', $columnNames, true);

$hasSortOrder =
    in_array('sort_order', $columnNames, true);


/*
|--------------------------------------------------------------------------
| Slug
|--------------------------------------------------------------------------
*/

function categorySlug($text)
{
    $text =
        strtolower(trim($text));

    $text =
        preg_replace(
            '/[^a-z0-9]+/',
            '-',
            $text
        );

    $text =
        trim($text, '-');

    return $text !== ''
        ? $text
        : 'kategori';
}


/*
|--------------------------------------------------------------------------
| Buat slug unik
|--------------------------------------------------------------------------
*/

function uniqueCategorySlug(
    $pdo,
    $name,
    $excludeId = 0
) {

    $base =
        categorySlug($name);

    $slug = $base;

    $number = 1;


    while (true) {


        if ($excludeId > 0) {

            $stmt = $pdo->prepare("
                SELECT id
                FROM categories
                WHERE slug = ?
                  AND id != ?
                LIMIT 1
            ");

            $stmt->execute([
                $slug,
                $excludeId
            ]);

        } else {

            $stmt = $pdo->prepare("
                SELECT id
                FROM categories
                WHERE slug = ?
                LIMIT 1
            ");

            $stmt->execute([
                $slug
            ]);

        }


        if (!$stmt->fetch()) {
            return $slug;
        }


        $number++;

        $slug =
            $base
            . '-'
            . $number;

    }

}


/*
|--------------------------------------------------------------------------
| Action
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    $action =
        $_POST['action'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | TAMBAH
    |--------------------------------------------------------------------------
    */

    if ($action === 'create') {


        $name =
            trim(
                $_POST['name'] ?? ''
            );

        $sortOrder =
            (int) (
                $_POST['sort_order']
                ?? 0
            );


        if ($name === '') {

            $error =
                'Nama kategori wajib diisi.';

        } else {


            try {


                $fields = [
                    'name'
                ];

                $values = [
                    $name
                ];


                if ($hasSlug) {

                    $fields[] = 'slug';

                    $values[] =
                        uniqueCategorySlug(
                            $pdo,
                            $name
                        );

                }


                if ($hasSortOrder) {

                    $fields[] =
                        'sort_order';

                    $values[] =
                        $sortOrder;

                }


                $placeholders =
                    implode(
                        ', ',
                        array_fill(
                            0,
                            count($fields),
                            '?'
                        )
                    );


                $sql =
                    'INSERT INTO categories ('
                    . implode(', ', $fields)
                    . ') VALUES ('
                    . $placeholders
                    . ')';


                $stmt =
                    $pdo->prepare($sql);


                $stmt->execute(
                    $values
                );


                $success =
                    'Kategori berhasil ditambahkan.';


            } catch (PDOException $e) {

                $error =
                    'Kategori gagal ditambahkan: '
                    . $e->getMessage();

            }

        }

    }


    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'edit') {


        $categoryId =
            (int) (
                $_POST['category_id']
                ?? 0
            );

        $name =
            trim(
                $_POST['name'] ?? ''
            );

        $sortOrder =
            (int) (
                $_POST['sort_order']
                ?? 0
            );


        if (
            $categoryId <= 0
            ||
            $name === ''
        ) {

            $error =
                'Data kategori tidak valid.';

        } else {


            $updates = [
                'name = ?'
            ];

            $values = [
                $name
            ];


            if ($hasSlug) {

                $updates[] =
                    'slug = ?';

                $values[] =
                    uniqueCategorySlug(
                        $pdo,
                        $name,
                        $categoryId
                    );

            }


            if ($hasSortOrder) {

                $updates[] =
                    'sort_order = ?';

                $values[] =
                    $sortOrder;

            }


            $values[] =
                $categoryId;


            $stmt =
                $pdo->prepare(
                    "
                    UPDATE categories
                    SET
                    "
                    . implode(
                        ', ',
                        $updates
                    )
                    . "
                    WHERE id = ?
                    "
                );


            $stmt->execute(
                $values
            );


            $success =
                'Kategori berhasil diperbarui.';

        }

    }


    /*
    |--------------------------------------------------------------------------
    | HAPUS
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'delete') {


        $categoryId =
            (int) (
                $_POST['category_id']
                ?? 0
            );


        $productCheck =
            $pdo->prepare("
                SELECT COUNT(*)
                FROM products
                WHERE category_id = ?
            ");


        $productCheck->execute([
            $categoryId
        ]);


        $productCount =
            (int)
            $productCheck
                ->fetchColumn();


        if ($productCount > 0) {

            $error =
                'Kategori tidak bisa dihapus karena masih digunakan oleh menu.';

        } else {


            $delete =
                $pdo->prepare("
                    DELETE FROM categories
                    WHERE id = ?
                ");


            $delete->execute([
                $categoryId
            ]);


            $success =
                'Kategori berhasil dihapus.';

        }

    }

}


/*
|--------------------------------------------------------------------------
| Ambil categories
|--------------------------------------------------------------------------
*/

$orderSql =
    $hasSortOrder
        ? 'sort_order ASC, name ASC'
        : 'name ASC';


$stmt =
    $pdo->query("
        SELECT *
        FROM categories
        ORDER BY $orderSql
    ");


$categories =
    $stmt->fetchAll();

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
        Kelola Kategori - WASIBU
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="../assets/css/style.css">

</head>


<body class="admin-shell admin-categories">


<?php require __DIR__ . '/navbar.php'; ?>


<main class="container py-5">


    <h2 class="fw-bold">
        Kelola Kategori
    </h2>

    <p class="text-secondary mb-4">
        Tambah dan ubah kategori menu.
    </p>


    <?php if ($error !== ''): ?>

        <div class="alert alert-danger">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <?php if ($success !== ''): ?>

        <div class="alert alert-success">

            <?= htmlspecialchars($success) ?>

        </div>

    <?php endif; ?>



    <div class="row g-4">


        <!-- TAMBAH -->

        <div class="col-lg-4">

            <div
                class="card border-0 shadow-sm rounded-4"
            >

                <div class="card-body p-4">


                    <h5 class="fw-bold mb-3">
                        Tambah Kategori
                    </h5>


                    <form method="POST">


                        <input
                            type="hidden"
                            name="action"
                            value="create"
                        >


                        <div class="mb-3">

                            <label
                                class="form-label"
                            >
                                Nama
                            </label>

                            <input
                                type="text"
                                name="name"
                                class="form-control"
                                required
                            >

                        </div>


                        <?php if ($hasSortOrder): ?>

                            <div class="mb-3">

                                <label
                                    class="form-label"
                                >
                                    Urutan
                                </label>

                                <input
                                    type="number"
                                    name="sort_order"
                                    class="form-control"
                                    value="0"
                                >

                            </div>

                        <?php endif; ?>


                        <button
                            class="btn btn-dark rounded-pill w-100"
                        >
                            Tambah Kategori
                        </button>


                    </form>


                </div>

            </div>

        </div>



        <!-- LIST -->

        <div class="col-lg-8">


            <?php foreach (
                $categories as $category
            ): ?>


                <div
                    class="card border-0 shadow-sm rounded-4 mb-3"
                >

                    <div class="card-body p-4">


                        <form method="POST">


                            <input
                                type="hidden"
                                name="action"
                                value="edit"
                            >

                            <input
                                type="hidden"
                                name="category_id"
                                value="<?= $category['id'] ?>"
                            >


                            <div class="row g-2">


                                <div class="col-md">

                                    <input
                                        type="text"
                                        name="name"
                                        class="form-control"
                                        value="<?= htmlspecialchars(
                                            $category['name']
                                        ) ?>"
                                        required
                                    >

                                </div>


                                <?php if ($hasSortOrder): ?>

                                    <div class="col-md-2">

                                        <input
                                            type="number"
                                            name="sort_order"
                                            class="form-control"
                                            value="<?= (int)
                                                $category[
                                                    'sort_order'
                                                ]
                                            ?>"
                                        >

                                    </div>

                                <?php endif; ?>


                                <div class="col-md-auto">

                                    <button
                                        class="btn btn-dark"
                                    >
                                        Simpan
                                    </button>

                                </div>


                            </div>


                        </form>


                        <form
                            method="POST"
                            class="mt-2"
                            onsubmit="return confirm('Hapus kategori ini?');"
                        >

                            <input
                                type="hidden"
                                name="action"
                                value="delete"
                            >

                            <input
                                type="hidden"
                                name="category_id"
                                value="<?= $category['id'] ?>"
                            >

                            <button
                                class="btn btn-link text-danger p-0 text-decoration-none"
                            >
                                Hapus
                            </button>

                        </form>


                    </div>

                </div>


            <?php endforeach; ?>


        </div>


    </div>


</main>


</body>

</html>