<?php

function promoSchemaReady(PDO $pdo): bool
{
    try {
        $price = $pdo->query("SHOW COLUMNS FROM promos LIKE 'promo_price'");
        $weekday = $pdo->query("SHOW COLUMNS FROM promos LIKE 'active_weekdays'");
        $quantity = $pdo->query("SHOW COLUMNS FROM promo_products LIKE 'quantity'");
        return (bool) $price->fetch() && (bool) $weekday->fetch() && (bool) $quantity->fetch();
    } catch (Throwable $exception) {
        return false;
    }
}

function promoAvailableNow(array $promo): bool
{
    if (!(int) ($promo['is_active'] ?? 0)) {
        return false;
    }

    $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Jakarta'));
    if (!empty($promo['starts_at']) && $promo['starts_at'] > $today->format('Y-m-d')) {
        return false;
    }
    if (!empty($promo['ends_at']) && $promo['ends_at'] < $today->format('Y-m-d')) {
        return false;
    }

    $weekdayMap = [
        1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu',
        5 => 'fri', 6 => 'sat', 7 => 'sun'
    ];
    $activeWeekdays = trim((string) ($promo['active_weekdays'] ?? ''));
    if ($activeWeekdays !== '') {
        $allowed = array_filter(array_map('trim', explode(',', strtolower($activeWeekdays))));
        if (!in_array($weekdayMap[(int) $today->format('N')], $allowed, true)) {
            return false;
        }
    }

    return true;
}
