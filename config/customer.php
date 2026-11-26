<?php

require_once __DIR__ . '/database.php';

function customerCsrfToken(): string
{
    if (empty($_SESSION['customer_csrf'])) {
        $_SESSION['customer_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['customer_csrf'];
}

function customerCsrfValidate(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['customer_csrf'])
        && hash_equals($_SESSION['customer_csrf'], $token);
}

function customerEnsureGuestToken(): string
{
    $token = $_COOKIE['wasibu_guest'] ?? '';
    if (!is_string($token) || !preg_match('/\A[a-f0-9]{64}\z/D', $token)) {
        $token = bin2hex(random_bytes(32));
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie('wasibu_guest', $token, [
            'expires' => time() + (365 * 24 * 60 * 60),
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['wasibu_guest'] = $token;
    }
    return $token;
}

function customerGuestHash(): string
{
    return hash('sha256', customerEnsureGuestToken());
}

function customerOrdersColumnsExist(PDO $pdo): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        $columns = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
        $ready = in_array('customer_id', $columns, true)
            && in_array('guest_token_hash', $columns, true);
    } catch (Throwable $e) {
        $ready = false;
    }
    return $ready;
}
