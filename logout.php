<?php
session_start();
if (!isset($_SESSION['customer_id'])) {
    header('Location: index.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !isset($_SESSION['customer_csrf'], $_POST['csrf_token'])
    || !hash_equals($_SESSION['customer_csrf'], (string) $_POST['csrf_token'])) {
    http_response_code(400);
    exit('Permintaan logout tidak valid.');
}
unset($_SESSION['customer_id'], $_SESSION['customer_username']);
session_regenerate_id(true);
header('Location: index.php');
exit;
