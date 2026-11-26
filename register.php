<?php
session_start();
require_once 'config/navigation.php';
require_once 'config/customer.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!customerCsrfValidate($_POST['csrf_token'] ?? null)) {
        $error = 'Sesi formulir tidak valid. Silakan coba lagi.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        if (!preg_match('/\A[A-Za-z0-9_.-]{3,80}\z/D', $username)) {
            $error = 'Username 3-80 karakter: huruf, angka, titik, garis bawah, atau strip.';
        } elseif (strlen($password) < 6) {
            $error = 'Password minimal 6 karakter.';
        } elseif (strlen($phone) > 30) {
            $error = 'Nomor HP maksimal 30 karakter.';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $insert = $pdo->prepare("INSERT INTO customers (username, password_hash, name, phone) VALUES (?, ?, ?, ?)");
                $insert->execute([$username, $hash, $username, $phone !== '' ? $phone : null]);
                session_regenerate_id(true);
                $_SESSION['customer_id'] = (int) $pdo->lastInsertId();
                $_SESSION['customer_username'] = $username;
                header('Location: cart.php');
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $error = 'Username sudah digunakan. Pilih username lain.';
                } elseif ($e->getCode() === '42S02') {
                    $error = 'Tabel customer belum ada di database. Jalankan migrasi customer terlebih dahulu.';
                } else {
                    error_log('Customer registration failed: ' . $e->getMessage());
                    $error = 'Pendaftaran gagal karena database tidak dapat menyimpan akun. Coba lagi nanti.';
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Daftar - WASIBU</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="customer-app login-page">
    <main class="container py-5 login-layout">
        <div class="row justify-content-center align-items-center login-row" style="min-height: 80vh;">
            <div class="col-12 col-md-6 col-lg-4 login-column">
                <div class="card login-card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <img src="assets/images/logo/wasibu.png" class="login-logo" alt="Logo WASIBU">
                            <h2 class="login-title fw-bold">Daftar</h2>
                        </div>
                        <?php if ($error !== ''): ?>
                            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                        <form method="post" autocomplete="on" class="login-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(customerCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="username">Username</label>
                                <input class="form-control form-control-lg" id="username" name="username" maxlength="80" autocomplete="username" value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="password">Password</label>
                                <input type="password" class="form-control form-control-lg" id="password" name="password" minlength="6" autocomplete="new-password" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-semibold" for="phone">Nomor HP <span class="text-secondary">(opsional)</span></label>
                                <input type="tel" class="form-control form-control-lg" id="phone" name="phone" maxlength="30" autocomplete="tel" value="<?= htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <button class="btn btn-dark w-100 rounded-pill py-3" type="submit">Daftar</button>
                        </form>
                        <p class="text-center mt-3 mb-0">Sudah punya akun? <a href="login.php">Login</a></p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
