<?php
require_once "config.php";

$merchant_id = '52503';
$secret_word_2 = 'AuraMarket';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $merchant = $_POST['MERCHANT_ID'];
    $amount = $_POST['AMOUNT'];
    $order_id = $_POST['MERCHANT_ORDER_ID'];
    $sign = $_POST['SIGN'];

    $sign_check = md5($merchant_id . ':' . $amount . ':' . $secret_word_2 . ':' . $order_id);

    if ($sign != $sign_check) {
        die('Wrong sign');
    }

    list($timestamp, $user_id) = explode('_', $order_id);
    
    $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    $stmt->bind_param("di", $amount, $user_id);
    $stmt->execute();
    $stmt->close();

    echo 'YES';
} else {
    die('Access denied');
}