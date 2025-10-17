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
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'inbox';
$chat_user_id = isset($_GET['chat_user_id']) ? intval($_GET['chat_user_id']) : 0;

if ($user_id > 0) {
    // Блокуємо користувача
    $block_reason = "Заблоковано модератором. Причина: " . $reason;
    $stmt = $conn->prepare("UPDATE users SET blocked = 1, block_reason = ? WHERE id = ?");
    $stmt->bind_param("si", $block_reason, $user_id);
    $stmt->execute();
    
    // Відправляємо повідомлення користувачу (додаємо sender_id)
    $message = "Ваш акаунт було заблоковано.\n\nПричина: " . $reason . "\n\nДля вирішення питання зв'яжіться з модератором через сторінку контактів.";
    $stmt = $conn->prepare("INSERT INTO moderator_messages (user_id, moderator_id, sender_id, message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $user_id, $moderator_id, $moderator_id, $message);
    $stmt->execute();
}

// Редірект
if ($redirect === 'chat' && $chat_user_id > 0) {
    header('Location: moderator_chat_user.php?user_id=' . $chat_user_id);
} else {
    header('Location: moderator_inbox.php');
}
exit();
?>