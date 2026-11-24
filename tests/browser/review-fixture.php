<?php
// Only synthetic fixture rows in the disposable PHPUnit database.
$db = new mysqli('127.0.0.1', 'root', '', 'pasa_buy_test');
$email = 'review-browser@example.test';
$action = $argv[1] ?? '';
if ($action === 'create') {
    $stmt = $db->prepare('INSERT INTO users (first_name,last_name,email,phone_number,password,address) VALUES (?,?,?,?,?,?)');
    $first='Browser'; $last='Review'; $phone='09999990001';
    $password=password_hash('ReviewPass#2026', PASSWORD_DEFAULT); $address='Synthetic test address';
    $stmt->bind_param('ssssss',$first,$last,$email,$phone,$password,$address);
} elseif ($action === 'suspend') {
    $stmt=$db->prepare("UPDATE users SET status='suspended' WHERE email=?");
    $stmt->bind_param('s',$email);
} elseif ($action === 'remove') {
    $stmt=$db->prepare('DELETE FROM users WHERE email=?');
    $stmt->bind_param('s',$email);
} else { throw new RuntimeException('Unknown fixture action'); }
$stmt->execute();
