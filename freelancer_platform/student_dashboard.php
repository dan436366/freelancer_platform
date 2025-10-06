<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['user_id'];

// Отримуємо заявки клієнта
$stmt = $conn->prepare("
    SELECT lr.id, lr.status, lr.created_at, u.name AS tutor_name, u.email as tutor_email,
           COUNT(m.id) as message_count
    FROM lesson_requests lr
    JOIN users u ON lr.tutor_id = u.id
    LEFT JOIN messages m ON lr.id = m.request_id
    WHERE lr.student_id = ?
    GROUP BY lr.id, lr.status, lr.created_at, u.name, u.email
    ORDER BY lr.created_at DESC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$requests = $stmt->get_result();

// Статистика для клієнта
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'Очікує' THEN 1 ELSE 0 END) as pending_requests,
        SUM(CASE WHEN status = 'Прийнята' THEN 1 ELSE 0 END) as accepted_requests,
        SUM(CASE WHEN status = 'Відхилена' THEN 1 ELSE 0 END) as rejected_requests
    FROM lesson_requests 
    WHERE student_id = ?
");
$stats_stmt->bind_param("i", $student_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Кабінет клієнта</title>
    <link rel="stylesheet" href="css/student_dashboard_style.css">
    <style>
        
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="welcome-section">
                <div class="user-avatar">🧑</div>
                <div class="welcome-text">
                    <h1>Кабінет клієнта</h1>
                    <span class="role-badge">
                        Привіт, <?= htmlspecialchars($_SESSION['user_name']) ?>!
                    </span>
                </div>
            </div>
            <div class="header-actions">
                <a href="dashboard.php" class="btn btn-primary">
                    🏠 Головна
                </a>
                <a href="my_ratings.php" class="btn btn-primary">
                    ⭐ Мої відгуки
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
                <h3 class="section-title">📋 Ваші заявки</h3>
            </div>

            <?php if ($requests->num_rows > 0): ?>
                <div class="requests-container">
                    <?php while ($row = $requests->fetch_assoc()): ?>
                        <div class="request-card animate-fade-in">
                            <div class="request-header">
                                <div class="tutor-info">
                                    <div class="tutor-name">
                                        👨‍💻 <?= htmlspecialchars($row['tutor_name']) ?>
                                        <?php if ($row['message_count'] > 0): ?>
                                            <span class="message-count"><?= $row['message_count'] ?> 💬</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="tutor-email">📧 <?= htmlspecialchars($row['tutor_email']) ?></div>
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
                                        <?php
                                        switch($row['status']) {
                                            case 'Очікує': echo 'Очікує'; break;
                                            case 'Прийнята': echo 'Прийнято'; break;
                                            case 'Відхилена': echo 'Відхилено'; break;
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>

                            <div class="request-actions">
                                <?php if ($row['status'] === 'Прийнята'): ?>
                                    <a href="chat.php?request_id=<?= $row['id'] ?>" class="btn-sm btn-chat">
                                        💬 Відкрити чат
                                    </a>
                                    <a href="rate_tutor.php?request_id=<?= $row['id'] ?>" class="btn-sm btn-rate">
                                        ⭐ Оцінити фрілансера
                                    </a>
                                <?php elseif ($row['status'] === 'Очікує'): ?>
                                    <span class="btn-sm btn-disabled">
                                        ⏳ Очікує підтвердження
                                    </span>
                                <?php else: ?>
                                    <span class="btn-sm btn-disabled">
                                        ❌ Заявку відхилено
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <h4 class="empty-title">У вас поки що немає заявок</h4>
                    <p class="empty-desc">
                        Знайдіть найкращого фрілансера та надішліть заявку<br>
                        Після підтвердження ви зможете розпочати
                    </p>
                    <div class="empty-action">
                        <a href="tutors.php" class="btn">
                            🔍 Знайти фрілансера
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="js/student_dashboard.js"></script>
</body>
</html>