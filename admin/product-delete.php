<?php

session_start();

require_once __DIR__ . '/auth.php';
require '../config/database.php';

// Ambil id produk

if (!isset($_GET['id'])) {

    header('Location: products.php');
    exit;

}


$id = (int) $_GET['id'];


// Hapus produk

$stmt = $pdo->prepare("
    DELETE FROM products
    WHERE id = ?
");


$stmt->execute([$id]);


// kembali ke halaman produk

header('Location: products.php?deleted=1');
exit;