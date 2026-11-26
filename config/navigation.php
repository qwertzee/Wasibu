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
    $basePath = trim(str_replace('\\', '/', wasibuBasePath()), '/');
    $normalizedPath = ltrim(str_replace('\\', '/', $path), '/');

    return ($basePath !== '' ? '/' . $basePath : '') . '/' . $normalizedPath;
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

function wasibuCustomerNavActions(bool $includeOrders = true, bool $iconOnlyCart = false): string
{
    $cartCount = array_sum($_SESSION['cart'] ?? []);
    $ordersUrl = htmlspecialchars(wasibuUrl('pesanan-saya.php'), ENT_QUOTES, 'UTF-8');
    $cartUrl = htmlspecialchars(wasibuUrl('cart.php'), ENT_QUOTES, 'UTF-8');
    $loginUrl = htmlspecialchars(wasibuUrl('login.php'), ENT_QUOTES, 'UTF-8');
    $registerUrl = htmlspecialchars(wasibuUrl('register.php'), ENT_QUOTES, 'UTF-8');
    $logoutUrl = htmlspecialchars(wasibuUrl('logout.php'), ENT_QUOTES, 'UTF-8');
    $html = '';
    if ($includeOrders) {
        $html .= '<a href="' . $ordersUrl . '" class="customer-nav-action" data-customer-nav="orders">Pesanan Saya</a>';
    }
    $cartLabel = $iconOnlyCart
        ? '<span class="visually-hidden">Keranjang</span>'
        : 'Keranjang';
    $html .= '<a href="' . $cartUrl . '" class="customer-nav-action" data-customer-nav="cart" aria-label="Keranjang"><i class="bi bi-cart3" aria-hidden="true"></i>' . $cartLabel;
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

function wasibuCustomerMobileNavbar(): string
{
    $menuItems = [
        ['index.php', 'home', 'bi-house-door', 'Beranda'],
        ['index.php#tentang', 'about', 'bi-info-circle', 'Tentang Kami'],
        ['index.php#kontak', 'contact', 'bi-telephone', 'Kontak'],
        ['daftar-menu.php', 'menu', 'bi-grid', 'Menu'],
        ['pesanan-saya.php', 'orders', 'bi-bag-check', 'Pesanan Saya'],
    ];

    $html = '<div class="customer-navbar-mobile">';
    $html .= '<button type="button" class="customer-mobile-menu-toggle" data-customer-menu-open aria-label="Buka navigasi" aria-expanded="false" aria-controls="customer-mobile-sidebar"><i class="bi bi-list" aria-hidden="true"></i></button>';
    $html .= wasibuLogoLink('customer-mobile-brand');
    $html .= '<nav class="customer-nav-actions customer-nav-actions-mobile" aria-label="Akun dan keranjang">';
    $html .= wasibuCustomerNavActions(false, true);
    $html .= '</nav></div>';

    $html .= '<button type="button" class="customer-sidebar-backdrop" data-customer-menu-close aria-label="Tutup navigasi" tabindex="-1"></button>';
    $html .= '<aside class="customer-mobile-sidebar" id="customer-mobile-sidebar" aria-label="Navigasi customer" aria-hidden="true" inert>';
    $html .= '<div class="customer-mobile-sidebar-header">' . wasibuLogoLink('customer-sidebar-brand') . '<button type="button" class="customer-mobile-menu-close" data-customer-menu-close aria-label="Tutup navigasi"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>';
    $html .= '<nav class="customer-mobile-sidebar-menu" aria-label="Menu customer">';
    foreach ($menuItems as [$path, $route, $icon, $label]) {
        $href = htmlspecialchars(wasibuUrl($path), ENT_QUOTES, 'UTF-8');
        $html .= '<a href="' . $href . '" class="customer-mobile-nav-link" data-customer-nav="' . $route . '"><i class="bi ' . $icon . '" aria-hidden="true"></i><span>' . $label . '</span></a>';
    }
    $html .= '</nav></aside>';

    return $html;
}