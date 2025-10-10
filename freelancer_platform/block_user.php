<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'moderator') {
    header('Location: moderator_login.php');
    exit();
}

$moderator_id = $_SESSION['user_id'];
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$reason = isset($_GET['reason']) ? htmlspecialchars($_GET['reason']) : 'Порушення правил платформи';

if ($user_id > 0) {
    // Блокуємо користувача
    $block_reason = "Заблоковано модератором. Причина: " . $reason;
    $stmt = $conn->prepare("UPDATE users SET blocked = 1, block_reason = ? WHERE id = ?");
    $stmt->bind_param("si", $block_reason, $user_id);
    $stmt->execute();
    
    // Відправляємо повідомлення користувачу
    $message = "Ваш акаунт було заблоковано.\n\nПричина: " . $reason . "\n\nДля вирішення питання зв'яжіться з модератором через сторінку контактів.";
    $stmt = $conn->prepare("INSERT INTO moderator_messages (user_id, moderator_id, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $user_id, $moderator_id, $message);
    $stmt->execute();
}

header('Location: moderator_inbox.php');
exit();
?>