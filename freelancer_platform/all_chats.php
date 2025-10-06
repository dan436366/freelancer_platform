<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT DISTINCT
        lr.id as request_id,
        lr.status,
        lr.created_at as request_created,
        s.name as student_name,
        s.id as student_id,
        t.name as tutor_name,
        t.id as tutor_id,
        (SELECT COUNT(*) FROM messages WHERE request_id = lr.id) as message_count,
        (SELECT message FROM messages WHERE request_id = lr.id ORDER BY sent_at DESC LIMIT 1) as last_message,
        (SELECT sent_at FROM messages WHERE request_id = lr.id ORDER BY sent_at DESC LIMIT 1) as last_message_time,
        (SELECT COUNT(*) FROM messages WHERE request_id = lr.id AND sender_id != ? AND seen = 0) as unread_count
    FROM lesson_requests lr
    JOIN users s ON lr.student_id = s.id
    JOIN users t ON lr.tutor_id = t.id
    WHERE (lr.student_id = ? OR lr.tutor_id = ?) 
    AND lr.status = 'Прийнята'
    AND EXISTS (SELECT 1 FROM messages WHERE request_id = lr.id)
    ORDER BY 
        (SELECT sent_at FROM messages WHERE request_id = lr.id ORDER BY sent_at DESC LIMIT 1) DESC,
        lr.created_at DESC
");
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$chats = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Мої чати</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/all_chats_style.css">
</head>
<body>

<div class="header">
    <a href="dashboard.php" class="back-link">
        ← Повернутись до головної
    </a>
    <h1>Мої чати</h1>
</div>

<div class="container">
    <div class="search-box">
        <input type="text" class="search-input" placeholder="Пошук чатів..." id="searchInput">
        <div class="search-icon">🔍</div>
    </div>

    <div class="chats-list" id="chatsList">
        <?php if ($chats->num_rows > 0): ?>
            <?php while ($chat = $chats->fetch_assoc()): ?>
                <?php
                $is_student = ($chat['student_id'] == $user_id);
                $chat_partner_name = $is_student ? $chat['tutor_name'] : $chat['student_name'];
                $chat_partner_role = $is_student ? 'tutor' : 'student';
                $my_role = $is_student ? 'student' : 'tutor';
                
                // кодування імені
                $chat_partner_name = html_entity_decode($chat_partner_name, ENT_QUOTES, 'UTF-8');
                ?>
                <a href="chat.php?request_id=<?= $chat['request_id'] ?>" class="chat-item" data-name="<?= htmlspecialchars($chat_partner_name, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="chat-avatar avatar-<?= $chat_partner_role ?>">
                        <?= mb_strtoupper(mb_substr($chat_partner_name, 0, 1, 'UTF-8'), 'UTF-8') ?>
                        
                        <?php if ($chat['unread_count'] > 0): ?>
                            <div class="unread-indicator">
                                <?= $chat['unread_count'] > 99 ? '99+' : $chat['unread_count'] ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="chat-info">
                        <div class="chat-header">
                            <div class="chat-name"><?= htmlspecialchars($chat_partner_name, ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="chat-meta">
                                <span class="role-badge role-<?= $chat_partner_role ?>">
                                    <?= $chat_partner_role == 'tutor' ? 'Фрілансер' : 'Клієнт' ?>
                                </span>
                                <?php if ($chat['last_message_time']): ?>
                                    <div class="chat-time">
                                        <?= date('d.m.Y H:i', strtotime($chat['last_message_time'])) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="chat-subject">
                            💬 Заявка #<?= $chat['request_id'] ?> • <?= date('d.m.Y', strtotime($chat['request_created'])) ?>
                        </div>
                        
                        <?php if ($chat['last_message']): ?>
                            <div class="chat-last-message">
                                <?= htmlspecialchars(mb_substr($chat['last_message'], 0, 60, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?><?= mb_strlen($chat['last_message'], 'UTF-8') > 60 ? '...' : '' ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 5px;">
                        <div class="message-count"><?= $chat['message_count'] ?> повідомлень</div>
                    </div>
                </a>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-chats">
                <h3>У вас поки немає чатів</h3>
                <p>Чати з'являться після прийняття заявок на уроки</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="js/all_chats.js"></script>

</body>
</html>