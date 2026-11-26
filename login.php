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
        $password = (string) ($_POST['password'] ?? '');
        try {
            $stmt = $pdo->prepare("SELECT id, username, password_hash FROM customers WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $customer = $stmt->fetch();
        } catch (PDOException $e) {
            if ($e->getCode() === '42S02') {
                $error = 'Tabel customer belum ada di database. Jalankan migrasi customer terlebih dahulu.';
            } else {
                error_log('Customer login lookup failed: ' . $e->getMessage());
                $error = 'Login tidak dapat diproses karena database tidak tersedia. Coba lagi nanti.';
            }
        }
        if ($error === '' && (!$customer || !password_verify($password, $customer['password_hash']))) {
            $error = 'Username atau password salah.';
        } elseif ($error === '') {
            session_regenerate_id(true);
            $_SESSION['customer_id'] = (int) $customer['id'];
            $_SESSION['customer_username'] = $customer['username'];
            header('Location: cart.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Login - WASIBU</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body class="customer-app login-page">
    <main class="container py-5 login-layout">
        <div class="row justify-content-center align-items-center login-row" style="min-height: 80vh;">
            <div class="col-12 col-md-6 col-lg-4 login-column">
                <div class="card login-card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <img src="assets/images/logo/wasibu.png" class="login-logo" alt="Logo WASIBU">
                            <h2 class="login-title fw-bold">Login</h2>
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
                            <div class="mb-4">
                                <label class="form-label fw-semibold" for="password">Password</label>
                                <input type="password" class="form-control form-control-lg" id="password" name="password" autocomplete="current-password" required>
                            </div>
                            <button class="btn btn-dark w-100 rounded-pill py-3" type="submit">Login</button>
                        </form>
                        <p class="text-center mt-3 mb-0">Belum punya akun? <a href="register.php">Daftar</a></p>
                        <a class="customer-back-home" href="<?= htmlspecialchars(wasibuUrl('index.php'), ENT_QUOTES, 'UTF-8') ?>">← Kembali ke Beranda</a>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
