<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'moderator') {
    header('Location: moderator_login.php');
    exit();
}

$moderator_id = $_SESSION['user_id'];
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id > 0) {
    // Розблоковуємо користувача
    $stmt = $conn->prepare("UPDATE users SET blocked = 0, block_reason = NULL WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Відправляємо повідомлення користувачу
    $message = "Ваш акаунт було розблоковано.\n\nВи знову маєте доступ до всіх функцій платформи. Будь ласка, дотримуйтесь правил користування сервісом.";
    $stmt = $conn->prepare("INSERT INTO moderator_messages (user_id, moderator_id, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $user_id, $moderator_id, $message);
    $stmt->execute();
}

header('Location: moderator_inbox.php');
exit();
?>