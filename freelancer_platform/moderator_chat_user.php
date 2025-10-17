<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'moderator') {
    header('Location: moderator_login.php');
    exit();
}

$moderator_id = $_SESSION['user_id'];
$moderator_name = $_SESSION['user_name'];
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Отримуємо інформацію про користувача
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role != 'moderator'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    die("Користувача не знайдено");
}

// Обробка надсилання повідомлення
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['message'])) {
    $message = htmlspecialchars(trim($_POST['message']));
    // Модератор відправляє повідомлення
    $stmt = $conn->prepare("INSERT INTO moderator_messages (user_id, moderator_id, sender_id, message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $user_id, $moderator_id, $moderator_id, $message);
    $stmt->execute();
    
    header("Location: moderator_chat_user.php?user_id=" . $user_id);
    exit();
}

// Отримуємо повідомлення
$stmt = $conn->prepare("
    SELECT mm.*, 
           sender.name as sender_name,
           sender.role as sender_role,
           CASE 
               WHEN sender.role = 'moderator' THEN 'moderator'
               ELSE 'user'
           END as sender_type,
           moderator.name as moderator_name,
           user.name as user_name
    FROM moderator_messages mm
    JOIN users sender ON mm.sender_id = sender.id
    JOIN users moderator ON mm.moderator_id = moderator.id
    JOIN users user ON mm.user_id = user.id
    WHERE mm.user_id = ?
    ORDER BY mm.created_at ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$messages = $stmt->get_result();

// Позначаємо повідомлення від користувача як прочитані
$stmt = $conn->prepare("UPDATE moderator_messages SET is_read = 1 WHERE user_id = ? AND moderator_id = ? AND is_read = 0");
$stmt->bind_param("ii", $user_id, $moderator_id);
$stmt->execute();
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Чат з <?= htmlspecialchars($user['name']) ?></title>
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
        
        .user-header-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
            margin: 0 20px;
        }
        
        .user-header-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            background: rgba(255,255,255,0.2);
        }
        
        .user-header-details h2 {
            margin: 0;
            font-size: 20px;
        }
        
        .user-header-meta {
            font-size: 13px;
            opacity: 0.9;
        }
        
        .blocked-indicator {
            background: #fee2e2;
            color: #991b1b;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
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
    </style>
</head>
<body>

<div class="chat-container">
    <div class="chat-header">
        <a href="moderator_inbox.php" class="back-link">
            ← Назад до чатів
        </a>
        
        <div class="user-header-info">
            <div class="user-header-avatar">
                <?= mb_substr($user['name'], 0, 1, 'UTF-8') ?>
            </div>
            <div class="user-header-details">
                <h2><?= htmlspecialchars($user['name']) ?></h2>
                <div class="user-header-meta">
                    <?= htmlspecialchars($user['email']) ?> | 
                    <?= $user['role'] === 'student' ? '🧑 Клієнт' : '👨‍💻 Фрілансер' ?>
                    <?php if ($user['blocked']): ?>
                        | <span class="blocked-indicator">🚫 ЗАБЛОКОВАНИЙ</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="header-actions">
            <?php if (!$user['blocked']): ?>
                <button onclick="blockUser()" class="btn btn-danger">
                    🚫 Заблокувати
                </button>
            <?php else: ?>
                <button onclick="unblockUser()" class="btn btn-success">
                    ✅ Розблокувати
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="chat-messages" id="chatMessages">
        <?php if ($messages->num_rows > 0): ?>
            <?php while ($msg = $messages->fetch_assoc()): ?>
                <div class="message <?= $msg['sender_type'] === 'moderator' ? 'own' : '' ?>">
                    <div class="message-avatar <?= $msg['sender_type'] ?>">
                        <?php if ($msg['sender_type'] === 'moderator'): ?>
                            👮
                        <?php else: ?>
                            <?= mb_substr($user['name'], 0, 1, 'UTF-8') ?>
                        <?php endif; ?>
                    </div>
                    <div class="message-content">
                        <div class="message-sender">
                            <?= $msg['sender_type'] === 'moderator' ? htmlspecialchars($msg['moderator_name']) : htmlspecialchars($msg['user_name']) ?>
                        </div>
                        <div class="message-text"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                        <div class="message-time"><?= date('d.m.Y H:i', strtotime($msg['created_at'])) ?></div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-chat">
                <div class="empty-chat-icon">💬</div>
                <p>Почніть розмову з користувачем</p>
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
    
    function blockUser() {
        if (confirm('Ви впевнені, що хочете заблокувати користувача "<?= htmlspecialchars($user['name']) ?>"?')) {
            const reason = prompt('Вкажіть причину блокування:');
            if (reason && reason.trim() !== '') {
                window.location.href = 'block_user.php?user_id=<?= $user_id ?>&reason=' + encodeURIComponent(reason) + '&redirect=chat&chat_user_id=<?= $user_id ?>';
            }
        }
    }
    
    function unblockUser() {
        if (confirm('Ви впевнені, що хочете розблокувати користувача "<?= htmlspecialchars($user['name']) ?>"?')) {
            window.location.href = 'unblock_user.php?user_id=<?= $user_id ?>&redirect=chat&chat_user_id=<?= $user_id ?>';
        }
    }
</script>

</body>
</html>