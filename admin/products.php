<?php

session_start();

require_once __DIR__ . '/auth.php';
require_once '../config/navigation.php';
require '../config/database.php';


/*
|--------------------------------------------------------------------------
| Ambil semua produk
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        p.*,
        c.name AS category_name
    FROM products p
    JOIN categories c
        ON c.id = p.category_id
    ORDER BY
        p.is_available DESC,
        p.id DESC
");

$products = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Menu WASIBU - Admin</title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >


    <style>

        body.admin-products {
            min-height: 100vh;
            background: var(--brand-background);
            color: var(--brand-primary-dark);
        }

        .admin-products main.container {
            max-width: 1200px;
            padding-top: 34px !important;
            padding-bottom: 52px !important;
        }

        .products-page-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 28px;
        }

        .products-page-heading h2 {
            margin-bottom: 4px;
            color: var(--brand-primary-dark);
            font-family: var(--font-heading);
            font-size: 1.9rem;
            font-weight: 400;
        }

        .products-page-heading p {
            margin: 0;
            color: #76585a;
            font-size: 0.95rem;
        }

        .products-grid-table {
            display: block;
            width: 100%;
            margin: 0;
        }

        .products-grid-table thead {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
        }

        .products-grid-table tbody {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 15px;
        }

        .products-grid-table tbody tr {
            display: flex;
            min-width: 0;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid rgba(65, 7, 9, 0.09);
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 5px 18px rgba(65, 7, 9, 0.055);
            transition: transform 170ms ease, box-shadow 170ms ease, border-color 170ms ease;
        }

        .products-grid-table tbody tr:hover {
            transform: translateY(-2px);
            border-color: rgba(157, 21, 29, 0.2);
            box-shadow: 0 10px 24px rgba(65, 7, 9, 0.095);
        }

        .products-grid-table tbody td {
            display: block;
            padding: 7px 15px;
            border: 0;
            color: #382729;
            font-size: 0.9rem;
        }

        .products-grid-table tbody td:first-child {
            height: 152px;
            padding: 0;
            background: #fff0ed;
        }

        .admin-product-image,
        .admin-product-placeholder {
            display: flex;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .admin-product-placeholder {
            justify-content: center;
            align-items: center;
            color: var(--brand-primary);
            font-size: 2.5rem;
        }

        .product-name {
            min-width: 0;
            padding-top: 10px !important;
            color: var(--brand-primary-dark) !important;
            font-size: 1.05rem !important;
            font-weight: 700;
        }

        .products-grid-table .product-name small {
            display: block;
            margin-top: 4px;
            font-size: 0.86rem;
            font-weight: 400;
            line-height: 1.35;
        }

        .products-grid-table tbody td:nth-child(3) {
            color: #76585a;
            font-size: 0.88rem;
        }

        .products-grid-table tbody td:nth-child(4) {
            color: var(--brand-primary);
            font-size: 1.05rem;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }

        .products-grid-table tbody td:nth-child(5) {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top: 1px solid rgba(65, 7, 9, 0.08);
            color: #76585a;
        }

        .products-grid-table tbody td:nth-child(5)::before {
            content: "Stok";
        }

        .products-grid-table tbody td:nth-child(6) {
            display: inline-flex;
            min-height: 30px;
            align-items: center;
        }

        .products-grid-table tbody td:nth-child(7) {
            display: flex;
            gap: 8px;
            margin-top: auto;
            padding: 8px 15px 12px;
        }

        .products-grid-table tbody td:nth-child(7) .btn {
            flex: 1 1 0;
            padding: 5px 10px;
            font-size: 0.86rem;
            font-weight: 600;
        }

        @media (max-width: 991.98px) {
            .products-grid-table tbody {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .admin-products main.container {
                padding: 24px 14px 38px !important;
            }

            .products-page-heading {
                align-items: stretch;
                flex-direction: column;
                margin-bottom: 21px;
            }

            .products-page-heading .btn {
                align-self: flex-start;
            }

            .products-grid-table tbody {
                grid-template-columns: minmax(0, 1fr);
                gap: 14px;
            }
        }

    </style>

</head>


<body class="admin-shell admin-products">


<!-- ============================================================= -->
<!-- NAVBAR -->
<!-- ============================================================= -->

<?php require __DIR__ . '/navbar.php'; ?>



<!-- ============================================================= -->
<!-- CONTENT -->
<!-- ============================================================= -->

<main class="container py-5">

        <?php if (isset($_GET['created'])): ?>

    <div
        class="alert alert-success alert-dismissible fade show"
        role="alert"
    >

        Menu baru berhasil ditambahkan.

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert"
        ></button>

    </div>

<?php endif; ?>

<?php if (isset($_GET['updated'])): ?>

    <div
        class="alert alert-success alert-dismissible fade show"
    >

        Menu berhasil diperbarui.

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert"
        ></button>

    </div>

<?php endif; ?>



    <!-- HEADER -->

    <div
        class="products-page-heading"
    >


        <div>

            <h2 class="fw-bold mb-1">
                Menu WASIBU
            </h2>

            <p class="text-secondary mb-0">
                Atur produk, harga, stok, dan kategori menu WASIBU.
            </p>

        </div>


        <a
            href="product-create.php"
            class="btn btn-dark rounded-pill px-4 py-2"
        >
            + Tambah Produk
        </a>


    </div>



    <!-- ========================================================= -->
    <!-- PRODUCT TABLE -->
    <!-- ========================================================= -->

    <div class="card admin-card shadow-sm">


        <div class="card-body p-4">


            <?php if (!empty($products)): ?>


                <div class="table-responsive">


                    <table
                        class="table admin-table products-grid-table align-middle"
                    >


                        <thead>

                            <tr>

                                <th>
                                    Foto
                                </th>

                                <th>
                                    Menu
                                </th>

                                <th>
                                    Kategori
                                </th>

                                <th>
                                    Harga
                                </th>

                                 <th>
                                    Stock
                                </th>

                                <th>
                                    Favorit
                                </th>

                                <th>
                                    Aksi
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach ($products as $product): ?>


                            <tr>


                                <!-- FOTO -->

                                <td>


                                   <?php

$imagePath = "../assets/images/products/" . $product['slug'] . ".jpg";

?>


<?php if (file_exists($imagePath)): ?>


    <img
        src="<?= htmlspecialchars($imagePath) ?>"
        alt="<?= htmlspecialchars($product['name']) ?>"
        class="admin-product-image"
        loading="lazy"
    >


<?php elseif (!empty($product['image'])): ?>


    <img
        src="../assets/images/products/<?= htmlspecialchars($product['image']) ?>"
        alt="<?= htmlspecialchars($product['name']) ?>"
        class="admin-product-image"
        loading="lazy"
    >


<?php else: ?>


    <div class="admin-product-placeholder">
        🍽️
    </div>


<?php endif; ?>
                                </td>



                                <!-- NAMA -->

                                <td class="product-name">


                                    <div class="fw-bold">

                                        <?= htmlspecialchars(
                                            $product['name']
                                        ) ?>

                                    </div>


                                    <?php if (
                                        !empty($product['description'])
                                    ): ?>


                                        <small
                                            class="text-secondary"
                                        >

                                            <?= htmlspecialchars(
                                                mb_strimwidth(
                                                    $product['description'],
                                                    0,
                                                    60,
                                                    '...'
                                                )
                                            ) ?>

                                        </small>


                                    <?php endif; ?>


                                </td>



                                <!-- KATEGORI -->

                                <td>

                                    <?= htmlspecialchars(
                                        $product['category_name']
                                    ) ?>

                                </td>



                                <!-- HARGA -->

                             <td>

    <strong>
        Rp<?= number_format(
            $product['base_price'],
            0,
            ',',
            '.'
        ) ?>
    </strong>

    <?php if (!empty($product['compare_at_price'])): ?>

        <br>

        <small class="text-muted text-decoration-line-through">
            Rp<?= number_format(
                $product['compare_at_price'],
                0,
                ',',
                '.'
            ) ?>
        </small>

    <?php endif; ?>

</td>


<td>
    <span class="product-stock-value"><?= htmlspecialchars($product['stock']) ?></span>
    <?php if ((int) $product['stock'] > 0): ?>
        <span class="badge text-bg-success">Tersedia</span>
    <?php else: ?>
        <span class="badge text-bg-danger">Habis</span>
    <?php endif; ?>
</td>
                                 


                                <!-- FAVORIT -->

                                <td>


                                    <?php if (
                                        (int) $product['is_featured'] === 1
                                    ): ?>


                                        <span
                                            class="badge text-bg-warning"
                                        >
                                            Favorit
                                        </span>


                                    <?php else: ?>


                                        <span class="text-secondary">
                                            -
                                        </span>


                                    <?php endif; ?>


                                </td>



                                <!-- AKSI -->

                                <td>


                                   <a
    href="product-edit.php?id=<?= $product['id'] ?>"
    class="btn btn-sm btn-outline-dark rounded-pill px-3"
>
    Edit
</a>


<a
    href="product-delete.php?id=<?= $product['id'] ?>"
    class="btn btn-sm btn-outline-danger rounded-pill px-3"
    onclick="return confirm('Yakin ingin menghapus <?= htmlspecialchars($product['name']) ?>?')"
>
    Hapus
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
                        🍽️
                    </div>


                    <h5 class="fw-bold">
                        Belum ada menu
                    </h5>


                    <p class="text-secondary">
                        Tambahkan menu pertama WASIBU.
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