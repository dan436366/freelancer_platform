<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;

// Перевіряємо, чи користувач має доступ до заявки
$stmt = $conn->prepare("SELECT * FROM lesson_requests WHERE id = ? AND (student_id = ? OR tutor_id = ?)");
$stmt->bind_param("iii", $request_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo "Немає доступу до цього чату.";
    exit();
}

$request_data = $result->fetch_assoc();

// Перевіряємо, чи заявка прийнята
if ($request_data['status'] !== 'Прийнята') {
    echo "Чат доступний тільки для прийнятих заявок.";
    exit();
}

// Позначаємо всі повідомлення від інших користувачів як прочитані
$stmt = $conn->prepare("UPDATE messages SET seen = 1 WHERE request_id = ? AND sender_id != ?");
$stmt->bind_param("ii", $request_id, $user_id);
$stmt->execute();

// Якщо надіслано повідомлення
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['message'])) {
    $msg = htmlspecialchars(trim($_POST['message']));
    $stmt = $conn->prepare("INSERT INTO messages (request_id, sender_id, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $request_id, $user_id, $msg);
    $stmt->execute();
    
    header("Location: chat.php?request_id=" . $request_id);
    exit();
}

// Отримуємо повідомлення
$stmt = $conn->prepare("
    SELECT m.*, u.name, u.role FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.request_id = ?
    ORDER BY m.sent_at ASC
");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$messages = $stmt->get_result();

// Отримуємо інформацію про учасників чату
$stmt = $conn->prepare("
    SELECT 
        lr.*,
        s.name as student_name,
        t.name as tutor_name
    FROM lesson_requests lr
    JOIN users s ON lr.student_id = s.id
    JOIN users t ON lr.tutor_id = t.id
    WHERE lr.id = ?
");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$chat_info = $stmt->get_result()->fetch_assoc();

function getFirstLetter($name) {
    $name = trim($name);
    if (empty($name)) {
        return '?';
    }
    
    $firstChar = mb_substr($name, 0, 1, 'UTF-8');
    return mb_strtoupper($firstChar, 'UTF-8');
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Чат</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/chat_style.css">
</head>
<body>

<div class="chat-container">
    <div class="chat-header">
        <a href="all_chats.php" class="back-link">
            ← Повернутись
        </a>
        <h2>Чат по заявці #<?= $request_id ?></h2>
        <div class="chat-info">
            Клієнт: <?= htmlspecialchars($chat_info['student_name']) ?> | 
            Фрілансер: <?= htmlspecialchars($chat_info['tutor_name']) ?>
        </div>
        <a href="submit_complaint.php?request_id=<?= $request_id ?>" class="btn btn-danger">
            ⚠️ Поскаржитись
        </a>
    </div>

    <div class="chat-messages" id="chatMessages">
        <?php if ($messages->num_rows > 0): ?>
            <?php while ($msg = $messages->fetch_assoc()): ?>
                <div class="message <?= $msg['sender_id'] == $user_id ? 'own' : '' ?>">
                    <div class="message-avatar avatar-<?= $msg['role'] ?>">
                        <?= getFirstLetter($msg['name']) ?>
                    </div>
                    <div class="message-content">
                        <div class="message-sender"><?= htmlspecialchars($msg['name']) ?></div>
                        <div class="message-text"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                        <div class="message-time"><?= date('d.m.Y H:i', strtotime($msg['sent_at'])) ?></div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-chat">
                <p>Поки що повідомлень немає. Почніть спілкування!</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="chat-input">
        <form method="post" class="input-form" id="messageForm">
            <textarea 
                name="message" 
                class="message-textarea" 
                placeholder="Введіть повідомлення..." 
                required
                id="messageInput"
                rows="1"
            ></textarea>
            <button type="submit" class="send-button" id="sendButton">
                ➤
            </button>
        </form>
    </div>
</div>

<script src="js/chat.js"></script>

</body>
</html>