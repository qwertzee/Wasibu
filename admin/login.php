<?php

session_start();

require '../config/database.php';


/*
|--------------------------------------------------------------------------
| Kalau sudah login
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['admin_id'])) {

    header('Location: dashboard.php');
    exit;

}


$error = '';


/*
|--------------------------------------------------------------------------
| Proses login
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim(
        $_POST['username'] ?? ''
    );

    $password =
        $_POST['password'] ?? '';


    if ($username === '' || $password === '') {

        $error =
            'Username dan password wajib diisi.';

    } else {


        /*
        |--------------------------------------------------------------------------
        | Cari admin
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT *
            FROM admins
            WHERE username = ?
            LIMIT 1
        ");

        $stmt->execute([
            $username
        ]);

        $admin = $stmt->fetch();


        /*
        |--------------------------------------------------------------------------
        | Verifikasi password
        |--------------------------------------------------------------------------
        */

        if (
            $admin
            &&
            password_verify(
                $password,
                $admin['password_hash']
            )
        ) {

            session_regenerate_id(true);


            $_SESSION['admin_id'] =
                $admin['id'];

            $_SESSION['admin_name'] =
                $admin['name'];

            $_SESSION['admin_username'] =
                $admin['username'];


            header(
                'Location: dashboard.php'
            );

            exit;


        } else {

            $error =
                'Username atau password salah.';

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
        Admin Panel - WASIBU
    </title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

</head>


<body class="admin-shell login-page">


<main class="container py-5 login-layout">


    <div
        class="row justify-content-center align-items-center login-row"
        style="min-height: 80vh;"
    >


        <div
            class="col-12 col-md-6 col-lg-4 login-column"
        >


            <div
                class="card admin-login-card login-card border-0 shadow-sm rounded-4"
            >


                <div class="card-body p-4 p-md-5">


                    <div class="text-center mb-4">

                        <img
                            src="../assets/images/logo/wasibu.png"
                            class="admin-login-logo login-logo"
                            alt="Logo WASIBU"
                        >


                        <h2 class="admin-login-title login-title fw-bold">
                            WASIBU
                        </h2>


                        <p class="text-secondary mb-0">
                            Admin Panel
                        </p>


                    </div>



                    <?php if ($error !== ''): ?>


                        <div
                            class="alert alert-danger"
                        >

                            <?= htmlspecialchars(
                                $error
                            ) ?>

                        </div>


                    <?php endif; ?>



                    <form
                        method="POST"
                        action=""
                        class="login-form"
                    >


                        <div class="mb-3">


                            <label
                                for="username"
                                class="form-label fw-semibold"
                            >
                                Username
                            </label>


                            <input
                                type="text"
                                id="username"
                                name="username"
                                class="form-control form-control-lg"
                                placeholder="Masukkan username"
                                required
                            >


                        </div>



                        <div class="mb-4">


                            <label
                                for="password"
                                class="form-label fw-semibold"
                            >
                                Password
                            </label>


                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control form-control-lg"
                                placeholder="Masukkan password"
                                required
                            >


                        </div>



                        <button
                            type="submit"
                            class="btn btn-dark w-100 rounded-pill py-3"
                        >
                            Masuk
                        </button>


                    </form>


                </div>

            </div>


        </div>

    </div>


</main>


</body>

</html>