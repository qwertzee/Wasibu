<?php

function orderStockRequirementsFromItems(array $items): array
{
    if (!$items) {
        throw new RuntimeException('Pesanan tidak memiliki item produk untuk diproses.');
    }

    $quantities = [];
    $variantProductIds = [];
    $promoMainIds = [];
    $promoComponentIds = [];

    foreach ($items as $item) {
        $quantity = (int) ($item['quantity'] ?? 0);
        $productId = $item['product_id'] === null ? null : (int) $item['product_id'];
        $variantId = $item['variant_id'] === null ? null : (int) $item['variant_id'];
        $promoId = $item['promo_id'] === null ? null : (int) $item['promo_id'];

        if ($quantity < 1) {
            throw new RuntimeException('Item pesanan tidak memiliki data produk atau jumlah yang valid.');
        }

        if ($promoId !== null) {
            if ($promoId < 1 || $variantId !== null) {
                throw new RuntimeException('Data item promo tidak valid.');
            }

            if ($productId === null) {
                $unitPrice = (float) $item['unit_price'];
                $subtotal = (float) $item['subtotal'];
                if ($unitPrice <= 0 || abs($subtotal - ($unitPrice * $quantity)) >= 0.01) {
                    throw new RuntimeException('Harga paket promo pada pesanan tidak valid.');
                }
                $promoMainIds[$promoId] = true;
                continue;
            }

            if ($productId < 1 || (float) $item['unit_price'] !== 0.0 || (float) $item['subtotal'] !== 0.0) {
                throw new RuntimeException('Data komponen paket promo tidak valid.');
            }
            $promoComponentIds[$promoId] = true;
        } else {
            if ($productId === null || $productId < 1) {
                throw new RuntimeException('Item pesanan tidak memiliki data produk atau jumlah yang valid.');
            }
            if ($variantId !== null) {
                if ($variantId < 1) {
                    throw new RuntimeException('Varian produk pada pesanan tidak valid.');
                }
                if (isset($variantProductIds[$variantId]) && $variantProductIds[$variantId] !== $productId) {
                    throw new RuntimeException('Varian produk tidak sesuai dengan produk pesanan.');
                }
                $variantProductIds[$variantId] = $productId;
            }
        }

        $quantities[$productId] = ($quantities[$productId] ?? 0) + $quantity;
    }

    foreach ($promoMainIds as $promoId => $_) {
        if (!isset($promoComponentIds[$promoId])) {
            throw new RuntimeException('Komponen paket promo tidak ditemukan pada snapshot pesanan.');
        }
    }
    foreach ($promoComponentIds as $promoId => $_) {
        if (!isset($promoMainIds[$promoId])) {
            throw new RuntimeException('Item utama paket promo tidak ditemukan pada snapshot pesanan.');
        }
    }

    if (!$quantities) {
        throw new RuntimeException('Pesanan tidak memiliki komponen produk untuk diproses.');
    }

    return [
        'quantities' => $quantities,
        'variant_product_ids' => $variantProductIds
    ];
}
