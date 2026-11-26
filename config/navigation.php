<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function wasibuBasePath(): string
{
    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
    $directory = dirname($scriptPath);

    if (basename($directory) === 'admin') {
        $directory = dirname($directory);
    }

    return $directory === '/' || $directory === '.'
        ? ''
        : rtrim($directory, '/');
}

function wasibuUrl(string $path): string
{
    return wasibuBasePath() . '/' . ltrim($path, '/');
}

function wasibuHomeUrl(): string
{
    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
    $currentDirectory = basename(dirname($scriptPath));

    return wasibuUrl(
        $currentDirectory === 'admin'
            ? 'admin/dashboard.php'
            : 'index.php'
    );
}

function wasibuLogoLink(string $class = 'navbar-brand'): string
{
    return sprintf(
        '<a href="%s" class="wasibu-navbar-brand %s"><img src="%s" class="wasibu-navbar-logo" alt="WASIBU"></a>',
        htmlspecialchars(wasibuHomeUrl(), ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($class, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars(wasibuUrl('assets/images/logo/wasibu.png'), ENT_QUOTES, 'UTF-8')
    );
}

function wasibuCustomerNavActions(): string
{
    $cartCount = array_sum($_SESSION['cart'] ?? []);
    $ordersUrl = htmlspecialchars(wasibuUrl('pesanan-saya.php'), ENT_QUOTES, 'UTF-8');
    $cartUrl = htmlspecialchars(wasibuUrl('cart.php'), ENT_QUOTES, 'UTF-8');
    $loginUrl = htmlspecialchars(wasibuUrl('login.php'), ENT_QUOTES, 'UTF-8');
    $registerUrl = htmlspecialchars(wasibuUrl('register.php'), ENT_QUOTES, 'UTF-8');
    $logoutUrl = htmlspecialchars(wasibuUrl('logout.php'), ENT_QUOTES, 'UTF-8');
    $html = '<a href="' . $ordersUrl . '" class="customer-nav-action" data-customer-nav="orders">Pesanan Saya</a>';
    $html .= '<a href="' . $cartUrl . '" class="customer-nav-action" data-customer-nav="cart" aria-label="Keranjang"><i class="bi bi-cart3" aria-hidden="true"></i> Keranjang';
    if ($cartCount > 0) {
        $html .= '<span class="wasibu-cart-badge">' . (int) $cartCount . '</span>';
    }
    $html .= '</a>';

    $html .= '<details class="customer-profile-menu">';
    $html .= '<summary class="customer-profile-trigger" aria-label="Profil customer" title="Profil customer"><i class="bi bi-person-circle" aria-hidden="true"></i></summary>';
    $html .= '<div class="customer-profile-dropdown">';
    if (!empty($_SESSION['customer_id'])) {
        if (empty($_SESSION['customer_csrf'])) {
            $_SESSION['customer_csrf'] = bin2hex(random_bytes(32));
        }
        $username = htmlspecialchars((string) ($_SESSION['customer_username'] ?? 'Customer'), ENT_QUOTES, 'UTF-8');
        $html .= '<span class="customer-profile-username">' . $username . '</span>';
        $html .= '<form method="post" action="' . $logoutUrl . '"><input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['customer_csrf'], ENT_QUOTES, 'UTF-8') . '"><button type="submit" class="customer-profile-link">Keluar</button></form>';
    } else {
        $html .= '<a href="' . $loginUrl . '" class="customer-profile-link">Masuk</a>';
        $html .= '<a href="' . $registerUrl . '" class="customer-profile-link">Daftar</a>';
    }
    $html .= '</div></details>';
    return $html;
}