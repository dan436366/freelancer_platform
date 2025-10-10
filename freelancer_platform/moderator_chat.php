<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'moderator') {
    header('Location: moderator_login.php');
    exit();
}

$moderator_id = $_SESSION['user_id'];
$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;
$complaint_id = isset($_GET['complaint_id']) ? intval($_GET['complaint_id']) : 0;

// Перевіряємо, чи є активна скарга по цій заявці
$stmt = $conn->prepare("
    SELECT c.*, lr.student_id, lr.tutor_id, lr.status as request_status
    FROM complaints c
    JOIN lesson_requests lr ON c.request_id = lr.id
    WHERE c.id = ? AND c.request_id = ? AND c.status IN ('Очікує', 'В обробці')
");
$stmt->bind_param("ii", $complaint_id, $request_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<!DOCTYPE html>
    <html lang='uk'>
    <head>
        <meta charset='UTF-8'>
        <title>Доступ заборонено</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                margin: 0;
            }
            .error-container {
                background: white;
                padding: 40px;
                border-radius: 12px;
                text-align: center;
                max-width: 500px;
            }
            .error-icon {
                font-size: 64px;
                margin-bottom: 20px;
            }
            h1 {
                color: #ef4444;
                margin: 0 0 10px 0;
            }
            p {
                color: #666;
                margin-bottom: 20px;
            }
            a {
                display: inline-block;
                padding: 10px 20px;
                background: #667eea;
                color: white;
                text-decoration: none;
                border-radius: 6px;
                transition: background 0.3s;
            }
            a:hover {
                background: #5568d3;
            }
        </style>
    </head>
    <body>
        <div class='error-container'>
            <div class='error-icon'>🚫</div>
            <h1>Доступ заборонено</h1>
            <p>Ви не маєте доступу до цього чату. Чат доступний лише при наявності активної скарги.</p>
            <a href='moderator_dashboard.php'>← Повернутись до панелі</a>
        </div>
    </body>
    </html>";
    exit();
}

$complaint_data = $result->fetch_assoc();

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
        s.email as student_email,
        t.name as tutor_name,
        t.email as tutor_email,
        complainant.name as complainant_name,
        against.name as against_name
    FROM lesson_requests lr
    JOIN users s ON lr.student_id = s.id
    JOIN users t ON lr.tutor_id = t.id
    JOIN complaints c ON c.request_id = lr.id
    JOIN users complainant ON c.complainant_id = complainant.id
    JOIN users against ON c.against_user_id = against.id
    WHERE lr.id = ? AND c.id = ?
");
$stmt->bind_param("ii", $request_id, $complaint_id);
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
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Перегляд чату - Модератор</title>
    <link rel="stylesheet" href="css/chat_style.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .moderator-header {
            background: #fef3c7;
            padding: 15px 20px;
            border-radius: 8px 8px 0 0;
            border-left: 4px solid #f59e0b;
        }
        
        .moderator-header h3 {
            margin: 0 0 10px 0;
            color: #92400e;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .complaint-info {
            font-size: 14px;
            color: #78350f;
        }
        
        .complaint-info strong {
            color: #92400e;
        }
        
        .chat-container {
            max-width: 900px;
            margin: 20px auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
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
        
        .participants-info {
            display: flex;
            gap: 20px;
            padding: 15px 20px;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .participant {
            flex: 1;
            padding: 10px;
            background: white;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
        }
        
        .participant.complainant {
            border-color: #fbbf24;
            background: #fffbeb;
        }
        
        .participant.against {
            border-color: #f87171;
            background: #fef2f2;
        }
        
        .participant-label {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 5px;
        }
        
        .participant.complainant .participant-label {
            color: #92400e;
        }
        
        .participant.against .participant-label {
            color: #991b1b;
        }
        
        .participant-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 3px;
        }
        
        .participant-email {
            font-size: 12px;
            color: #666;
        }
        
        .chat-messages {
            height: 500px;
            overflow-y: auto;
            padding: 20px;
            background: #f9fafb;
        }
        
        .message {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
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
        
        .avatar-student {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .avatar-tutor {
            background: #dcfce7;
            color: #166534;
        }
        
        .message-content {
            flex: 1;
            max-width: 70%;
        }
        
        .message-sender {
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
            font-size: 14px;
        }
        
        .message-text {
            background: white;
            padding: 10px 15px;
            border-radius: 12px;
            line-height: 1.5;
            color: #333;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .message-time {
            font-size: 11px;
            color: #999;
            margin-top: 5px;
        }
        
        .moderator-note-section {
            padding: 20px;
            background: #f0f9ff;
            border-top: 1px solid #e5e7eb;
        }
        
        .moderator-note-section h4 {
            margin: 0 0 10px 0;
            color: #1e40af;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .note-text {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
            color: #333;
            line-height: 1.6;
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
        
        .readonly-notice {
            padding: 15px 20px;
            background: #e0e7ff;
            border-top: 1px solid #c7d2fe;
            text-align: center;
            color: #3730a3;
            font-weight: 500;
        }
    </style>
</head>
<body>

<div class="chat-container">
    <div class="moderator-header">
        <h3>👮 Режим модератора</h3>
        <div class="complaint-info">
            <strong>Скарга #<?= $complaint_id ?></strong> | 
            Скаржник: <strong><?= htmlspecialchars($chat_info['complainant_name']) ?></strong> → 
            Порушник: <strong><?= htmlspecialchars($chat_info['against_name']) ?></strong>
        </div>
    </div>

    <div class="chat-header">
        <a href="moderator_dashboard.php" class="back-link">
            ← Повернутись до панелі
        </a>
        <h2 style="margin: 0;">Чат по заявці #<?= $request_id ?></h2>
    </div>

    <div class="participants-info">
        <div class="participant <?= $complaint_data['complainant_id'] == $chat_info['student_id'] ? 'complainant' : '' ?> <?= $complaint_data['against_user_id'] == $chat_info['student_id'] ? 'against' : '' ?>">
            <div class="participant-label">
                🎓 Студент
                <?php if ($complaint_data['complainant_id'] == $chat_info['student_id']): ?>
                    (Скаржник)
                <?php elseif ($complaint_data['against_user_id'] == $chat_info['student_id']): ?>
                    (Порушник)
                <?php endif; ?>
            </div>
            <div class="participant-name"><?= htmlspecialchars($chat_info['student_name']) ?></div>
            <div class="participant-email"><?= htmlspecialchars($chat_info['student_email']) ?></div>
        </div>

        <div class="participant <?= $complaint_data['complainant_id'] == $chat_info['tutor_id'] ? 'complainant' : '' ?> <?= $complaint_data['against_user_id'] == $chat_info['tutor_id'] ? 'against' : '' ?>">
            <div class="participant-label">
                👨‍🏫 Репетитор
                <?php if ($complaint_data['complainant_id'] == $chat_info['tutor_id']): ?>
                    (Скаржник)
                <?php elseif ($complaint_data['against_user_id'] == $chat_info['tutor_id']): ?>
                    (Порушник)
                <?php endif; ?>
            </div>
            <div class="participant-name"><?= htmlspecialchars($chat_info['tutor_name']) ?></div>
            <div class="participant-email"><?= htmlspecialchars($chat_info['tutor_email']) ?></div>
        </div>
    </div>

    <div class="chat-messages" id="chatMessages">
        <?php if ($messages->num_rows > 0): ?>
            <?php while ($msg = $messages->fetch_assoc()): ?>
                <div class="message">
                    <div class="message-avatar avatar-<?= $msg['role'] ?>">
                        <?= getFirstLetter($msg['name']) ?>
                    </div>
                    <div class="message-content">
                        <div class="message-sender">
                            <?= htmlspecialchars($msg['name']) ?>
                            <?php if ($msg['sender_id'] == $complaint_data['complainant_id']): ?>
                                <span style="color: #f59e0b; font-size: 12px;">⚠️ Скаржник</span>
                            <?php elseif ($msg['sender_id'] == $complaint_data['against_user_id']): ?>
                                <span style="color: #ef4444; font-size: 12px;">🚫 Порушник</span>
                            <?php endif; ?>
                        </div>
                        <div class="message-text"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                        <div class="message-time"><?= date('d.m.Y H:i', strtotime($msg['sent_at'])) ?></div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-chat">
                <div class="empty-chat-icon">💬</div>
                <p>У цьому чаті поки що немає повідомлень</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="readonly-notice">
        🔒 Режим лише для перегляду. Модератори не можуть надсилати повідомлення в чат.
    </div>
</div>

<script>
    // Автоматичне прокручування до останнього повідомлення
    window.addEventListener('load', function() {
        const chatMessages = document.getElementById('chatMessages');
        chatMessages.scrollTop = chatMessages.scrollHeight;
    });
</script>

</body>
</html>