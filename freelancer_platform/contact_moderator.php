<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Перевіряємо чи користувач заблокований
$stmt = $conn->prepare("SELECT blocked, block_reason FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

// Обробка надсилання повідомлення
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['message'])) {
    $message = htmlspecialchars(trim($_POST['message']));
    
    // Знаходимо будь-якого модератора
    $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'moderator' LIMIT 1");
    $stmt->execute();
    $moderator = $stmt->get_result()->fetch_assoc();
    
    if ($moderator) {
        $moderator_id = $moderator['id'];
        // Користувач відправляє повідомлення
        // user_id - отримувач розмови, moderator_id - модератор, sender_id - хто відправив
        $stmt = $conn->prepare("INSERT INTO moderator_messages (user_id, moderator_id, sender_id, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $user_id, $moderator_id, $user_id, $message);
        $stmt->execute();
    }
    
    header("Location: contact_moderator.php");
    exit();
}

// Отримуємо всі повідомлення між користувачем та модераторами
$stmt = $conn->prepare("
    SELECT mm.*, 
           sender.name as sender_name,
           sender.role as sender_role
    FROM moderator_messages mm
    JOIN users sender ON mm.sender_id = sender.id
    WHERE mm.user_id = ?
    ORDER BY mm.created_at ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$messages = $stmt->get_result();

// Позначаємо всі повідомлення як прочитані
$stmt = $conn->prepare("UPDATE moderator_messages SET is_read = 1 WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Зв'язок з модератором</title>
    <link rel="stylesheet" href="css/chat_style.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
        }
        
        .chat-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: calc(100vh - 40px);
        }
        
        <?php if ($user_data['blocked']): ?>
        .blocked-alert {
            background: #fee2e2;
            border-bottom: 3px solid #ef4444;
            padding: 20px;
        }
        
        .blocked-content {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .blocked-icon {
            font-size: 32px;
        }
        
        .blocked-text h3 {
            margin: 0 0 5px 0;
            color: #991b1b;
        }
        
        .blocked-text p {
            margin: 0;
            color: #7f1d1d;
            font-size: 14px;
        }
        <?php endif; ?>
        
        .chat-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .back-link {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 6px;
            transition: background 0.3s;
        }
        
        .back-link:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .chat-title {
            flex: 1;
            text-align: center;
        }
        
        .chat-title h2 {
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #f9fafb;
        }
        
        .message {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .message.own {
            flex-direction: row-reverse;
        }
        
        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 16px;
            flex-shrink: 0;
        }
        
        .message-avatar.moderator {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .message-avatar.user {
            background: #dcfce7;
            color: #166534;
        }
        
        .message-content {
            max-width: 60%;
        }
        
        .message.own .message-content {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }
        
        .message-sender {
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
            font-size: 14px;
        }
        
        .message.own .message-sender {
            text-align: right;
        }
        
        .message-text {
            background: white;
            padding: 12px 16px;
            border-radius: 12px;
            line-height: 1.5;
            color: #333;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .message.own .message-text {
            background: #667eea;
            color: white;
        }
        
        .message-time {
            font-size: 11px;
            color: #999;
            margin-top: 5px;
        }
        
        .message.own .message-time {
            text-align: right;
            color: #ccc;
        }
        
        .chat-input {
            padding: 20px;
            background: white;
            border-top: 1px solid #e5e7eb;
        }
        
        .input-form {
            display: flex;
            gap: 10px;
        }
        
        .message-textarea {
            flex: 1;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            resize: none;
            min-height: 50px;
            max-height: 150px;
        }
        
        .message-textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .send-button {
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0 20px;
            font-size: 18px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .send-button:hover {
            background: #5568d3;
        }
        
        .empty-chat {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-chat-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .info-box {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 20px;
        }
        
        .info-box h4 {
            margin: 0 0 10px 0;
            color: #92400e;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-box p {
            margin: 0;
            color: #78350f;
            line-height: 1.5;
            font-size: 14px;
        }
    </style>
</head>
<body>

<div class="chat-container">
    <?php if ($user_data['blocked']): ?>
    <div class="blocked-alert">
        <div class="blocked-content">
            <div class="blocked-icon">🚫</div>
            <div class="blocked-text">
                <h3>Ваш акаунт заблоковано</h3>
                <p>Напишіть модератору, щоб вирішити це питання</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="chat-header">
        <a href="dashboard.php" class="back-link">
            ← Назад
        </a>
        
        <div class="chat-title">
            <h2>👮 Чат з модератором</h2>
        </div>
        
        <div style="width: 80px;"></div>
    </div>

    <?php if (!$user_data['blocked']): ?>
    <div class="info-box">
        <h4>💡 Корисна інформація</h4>
        <p>Модератор відповість вам якомога швидше. Будьте ввічливими та чітко описуйте вашу проблему.</p>
    </div>
    <?php endif; ?>

    <div class="chat-messages" id="chatMessages">
        <?php if ($messages->num_rows > 0): ?>
            <?php while ($msg = $messages->fetch_assoc()): ?>
                <div class="message <?= $msg['sender_id'] == $user_id ? 'own' : '' ?>">
                    <div class="message-avatar <?= $msg['sender_role'] ?>">
                        <?php if ($msg['sender_role'] === 'moderator'): ?>
                            👮
                        <?php else: ?>
                            <?= mb_substr($user_name, 0, 1, 'UTF-8') ?>
                        <?php endif; ?>
                    </div>
                    <div class="message-content">
                        <div class="message-sender">
                            <?php if ($msg['sender_role'] === 'moderator'): ?>
                                Модератор <?= htmlspecialchars($msg['sender_name']) ?>
                            <?php else: ?>
                                Ви
                            <?php endif; ?>
                        </div>
                        <div class="message-text"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                        <div class="message-time"><?= date('d.m.Y H:i', strtotime($msg['created_at'])) ?></div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-chat">
                <div class="empty-chat-icon">💬</div>
                <h4>Поки що повідомлень немає</h4>
                <p>Напишіть ваше перше повідомлення модератору</p>
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
                rows="2"
            ></textarea>
            <button type="submit" class="send-button" id="sendButton">
                ➤
            </button>
        </form>
    </div>
</div>

<script>
    // Автоматичне прокручування до останнього повідомлення
    window.addEventListener('load', function() {
        const chatMessages = document.getElementById('chatMessages');
        chatMessages.scrollTop = chatMessages.scrollHeight;
    });
    
    // Автоматичне розширення textarea
    const textarea = document.getElementById('messageInput');
    textarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 150) + 'px';
    });
    
    // Надсилання по Ctrl+Enter
    textarea.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'Enter') {
            document.getElementById('messageForm').submit();
        }
    });
</script>

</body>
</html>