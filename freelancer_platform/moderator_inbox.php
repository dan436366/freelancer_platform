<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'moderator') {
    header('Location: moderator_login.php');
    exit();
}

$moderator_id = $_SESSION['user_id'];

// Отримуємо список користувачів, які мають повідомлення з модератором
$stmt = $conn->prepare("
    SELECT 
        u.id,
        u.name,
        u.email,
        u.role,
        u.blocked,
        COUNT(mm.id) as message_count,
        MAX(mm.created_at) as last_message_time,
        SUM(CASE WHEN mm.is_read = 0 AND mm.user_id = u.id THEN 1 ELSE 0 END) as unread_from_user
    FROM users u
    INNER JOIN moderator_messages mm ON (mm.user_id = u.id OR mm.moderator_id = u.id)
    WHERE u.role != 'moderator'
    GROUP BY u.id
    ORDER BY last_message_time DESC
");
$stmt->execute();
$users = $stmt->get_result();

// Статистика
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT user_id) as total_chats
    FROM moderator_messages 
    WHERE moderator_id = ?
");
$stmt->bind_param("i", $moderator_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Чати з користувачами - Модератор</title>
    <link rel="stylesheet" href="css/login_style.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            margin: 0;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .nav-links {
            display: flex;
            gap: 10px;
        }
        
        .nav-btn {
            padding: 8px 16px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: background 0.3s;
            font-size: 14px;
        }
        
        .nav-btn:hover {
            background: #5568d3;
        }
        
        .stats-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stat-icon {
            font-size: 32px;
        }
        
        .stat-info h3 {
            margin: 0;
            color: #667eea;
            font-size: 28px;
        }
        
        .stat-info p {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 14px;
        }
        
        .users-list {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .list-header {
            padding: 20px;
            border-bottom: 2px solid #f3f4f6;
        }
        
        .list-header h2 {
            margin: 0;
            color: #333;
        }
        
        .user-item {
            padding: 20px;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: background 0.2s;
            cursor: pointer;
        }
        
        .user-item:hover {
            background: #f9fafb;
        }
        
        .user-item:last-child {
            border-bottom: none;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            flex-shrink: 0;
        }
        
        .user-avatar.student {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .user-avatar.tutor {
            background: #dcfce7;
            color: #166534;
        }
        
        .user-info {
            flex: 1;
        }
        
        .user-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .blocked-badge {
            background: #fee2e2;
            color: #991b1b;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .user-meta {
            font-size: 13px;
            color: #666;
        }
        
        .user-stats {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .message-count {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #666;
            font-size: 14px;
        }
        
        .unread-badge {
            background: #ef4444;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .last-message-time {
            font-size: 12px;
            color: #999;
        }
        
        .user-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
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
        
        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: #666;
        }
        
        .empty-icon {
            font-size: 64px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💬 Чати з користувачами</h1>
            <div class="nav-links">
                <a href="moderator_dashboard.php" class="nav-btn">← Панель модератора</a>
            </div>
        </div>

        <div class="stats-card">
            <div class="stat-icon">📊</div>
            <div class="stat-info">
                <h3><?= $users->num_rows ?></h3>
                <p>Активних чатів</p>
            </div>
        </div>

        <div class="users-list">
            <div class="list-header">
                <h2>Список чатів</h2>
            </div>
            
            <?php if ($users->num_rows > 0): ?>
                <?php while ($user = $users->fetch_assoc()): ?>
                    <div class="user-item" onclick="window.location.href='moderator_chat_user.php?user_id=<?= $user['id'] ?>'">
                        <div class="user-avatar <?= $user['role'] ?>">
                            <?= mb_substr($user['name'], 0, 1, 'UTF-8') ?>
                        </div>
                        
                        <div class="user-info">
                            <div class="user-name">
                                <?= htmlspecialchars($user['name']) ?>
                                <?php if ($user['blocked']): ?>
                                    <span class="blocked-badge">🚫 ЗАБЛОКОВАНИЙ</span>
                                <?php endif; ?>
                            </div>
                            <div class="user-meta">
                                <?= htmlspecialchars($user['email']) ?> | 
                                ID: <?= $user['id'] ?> | 
                                <?= $user['role'] === 'student' ? '🎓 Студент' : '👨‍🏫 Репетитор' ?>
                            </div>
                        </div>
                        
                        <div class="user-stats">
                            <div class="message-count">
                                💬 <?= $user['message_count'] ?>
                            </div>
                            <?php if ($user['unread_from_user'] > 0): ?>
                                <span class="unread-badge"><?= $user['unread_from_user'] ?> нових</span>
                            <?php endif; ?>
                            <div class="last-message-time">
                                <?= date('d.m.Y H:i', strtotime($user['last_message_time'])) ?>
                            </div>
                        </div>
                        
                        <div class="user-actions" onclick="event.stopPropagation();">
                            <?php if (!$user['blocked']): ?>
                                <button onclick="blockUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>')" class="btn btn-danger">
                                    🚫 Заблокувати
                                </button>
                            <?php else: ?>
                                <button onclick="unblockUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>')" class="btn btn-success">
                                    ✅ Розблокувати
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <h3>Немає активних чатів</h3>
                    <p>Чати з'являться, коли користувачі напишуть модератору</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function blockUser(userId, userName) {
            if (confirm('Ви впевнені, що хочете заблокувати користувача "' + userName + '"?')) {
                const reason = prompt('Вкажіть причину блокування:');
                if (reason && reason.trim() !== '') {
                    window.location.href = 'block_user.php?user_id=' + userId + '&reason=' + encodeURIComponent(reason) + '&redirect=inbox';
                }
            }
        }
        
        function unblockUser(userId, userName) {
            if (confirm('Ви впевнені, що хочете розблокувати користувача "' + userName + '"?')) {
                window.location.href = 'unblock_user.php?user_id=' + userId + '&redirect=inbox';
            }
        }
    </script>
</body>
</html>