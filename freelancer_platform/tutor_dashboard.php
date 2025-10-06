<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tutor') {
    header('Location: login.php');
    exit();
}

$tutor_id = $_SESSION['user_id'];

// Отримуємо заявки на заняття
$stmt = $conn->prepare("
    SELECT lr.id, u.name AS student_name, u.email, lr.created_at, lr.status,
           COUNT(m.id) as message_count
    FROM lesson_requests lr
    JOIN users u ON lr.student_id = u.id
    LEFT JOIN messages m ON lr.id = m.request_id
    WHERE lr.tutor_id = ?
    GROUP BY lr.id, u.name, u.email, lr.created_at, lr.status
    ORDER BY lr.created_at DESC
");
$stmt->bind_param("i", $tutor_id);
$stmt->execute();
$result = $stmt->get_result();

// Статистика
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'Очікує' THEN 1 ELSE 0 END) as pending_requests,
        SUM(CASE WHEN status = 'Прийнята' THEN 1 ELSE 0 END) as accepted_requests,
        SUM(CASE WHEN status = 'Відхилена' THEN 1 ELSE 0 END) as rejected_requests
    FROM lesson_requests 
    WHERE tutor_id = ?
");
$stats_stmt->bind_param("i", $tutor_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Кабінет фрілансера</title>
    <link rel="stylesheet" href="css/tutor_dashboard_style.css">
    <style>
        
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="welcome-section">
                <div class="user-avatar">👨‍💻</div>
                <div class="welcome-text">
                    <h1>Кабінет фрілансера</h1>
                    <span class="role-badge">
                        Привіт, <?= htmlspecialchars($_SESSION['user_name']) ?>!
                    </span>
                </div>
            </div>
            <div class="header-actions">
                <a href="dashboard.php" class="btn btn-primary">
                    🏠 Головна
                </a>
                <a href="tutor_specializations.php" class="btn btn-primary">
                    🎯 Мої спеціалізації
                </a>
                <a href="logout.php" class="btn btn-outline">
                    🚪 Вийти
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                ✅ <?= htmlspecialchars($_SESSION['success_message']) ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                ❌ <?= htmlspecialchars($_SESSION['error_message']) ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Статистика -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">📊</div>
                </div>
                <div class="stat-number"><?= $stats['total_requests'] ?: 0 ?></div>
                <div class="stat-label">Всього заявок</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-header">
                    <div class="stat-icon">⏳</div>
                </div>
                <div class="stat-number"><?= $stats['pending_requests'] ?: 0 ?></div>
                <div class="stat-label">Очікують відповіді</div>
            </div>
            <div class="stat-card accepted">
                <div class="stat-header">
                    <div class="stat-icon">✅</div>
                </div>
                <div class="stat-number"><?= $stats['accepted_requests'] ?: 0 ?></div>
                <div class="stat-label">Прийнято</div>
            </div>
            <div class="stat-card rejected">
                <div class="stat-header">
                    <div class="stat-icon">❌</div>
                </div>
                <div class="stat-number"><?= $stats['rejected_requests'] ?: 0 ?></div>
                <div class="stat-label">Відхилено</div>
            </div>
        </div>

        <!-- Заявки -->
        <div class="requests-section">
            <div class="section-header">
                <h3 class="section-title">📋 Заявки на заняття</h3>
            </div>

            <?php if ($result->num_rows > 0): ?>
                <div class="requests-container">
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <div class="request-card animate-fade-in">
                            <div class="request-header">
                                <div class="student-info">
                                    <div class="student-name">
                                        🧑 <?= htmlspecialchars($row['student_name']) ?>
                                        <?php if ($row['message_count'] > 0): ?>
                                            <span class="message-count"><?= $row['message_count'] ?> 💬</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="student-email">📧 <?= htmlspecialchars($row['email']) ?></div>
                                </div>
                                <div class="request-meta">
                                    <div class="date-info">
                                        📅 <?= date('d.m.Y H:i', strtotime($row['created_at'])) ?>
                                    </div>
                                    <span class="status-badge status-<?php 
                                        switch($row['status']) {
                                            case 'Очікує': echo 'pending'; break;
                                            case 'Прийнята': echo 'accepted'; break;
                                            case 'Відхилена': echo 'rejected'; break;
                                        }
                                    ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </div>
                            </div>

                            <?php if (!empty($row['message'])): ?>
                                <div class="request-message">
                                    💭 <?= htmlspecialchars($row['message']) ?>
                                </div>
                            <?php endif; ?>

                            <div class="request-actions">
                                <?php if ($row['status'] === 'Очікує'): ?>
                                    <form action="update_request.php" method="post" style="display: inline;">
                                        <input type="hidden" name="request_id" value="<?= $row['id'] ?>">
                                        <input type="hidden" name="action" value="accept">
                                        <button type="submit" class="btn-sm btn-accept" onclick="return confirm('Прийняти заявку?')">
                                            ✅ Прийняти
                                        </button>
                                    </form>
                                    <form action="update_request.php" method="post" style="display: inline;">
                                        <input type="hidden" name="request_id" value="<?= $row['id'] ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn-sm btn-reject" onclick="return confirm('Відхилити заявку?')">
                                            ❌ Відхилити
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if ($row['status'] === 'Прийнята'): ?>
                                    <a href="chat.php?request_id=<?= $row['id'] ?>" class="btn-sm btn-chat">
                                        💬 Відкрити чат
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <h4 class="empty-title">Поки що заявок немає</h4>
                    <p class="empty-desc">
                        Коли клієнти надішлють заявки, вони з'являться тут.<br>
                        Ви зможете переглядати їх, приймати або відхиляти.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="js/tutor_dashboard.js"></script>
</body>
</html>