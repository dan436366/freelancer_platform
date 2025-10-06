<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require 'db.php';

$user_id = $_SESSION["user_id"];
$user_name = $_SESSION["user_name"];
$user_role = $_SESSION["role"];

$stats = [];

if ($user_role == "student") {
    // Статистика для клієнта
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN status = 'Очікує' THEN 1 ELSE 0 END) as pending_requests,
            SUM(CASE WHEN status = 'Прийнята' THEN 1 ELSE 0 END) as accepted_requests,
            SUM(CASE WHEN status = 'Відхилена' THEN 1 ELSE 0 END) as rejected_requests
        FROM lesson_requests 
        WHERE student_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    // Отримуємо останні активні заявки
    $recent_stmt = $conn->prepare("
        SELECT lr.*, u.name as tutor_name, u.email as tutor_email
        FROM lesson_requests lr
        JOIN users u ON lr.tutor_id = u.id
        WHERE lr.student_id = ?
        ORDER BY lr.created_at DESC
        LIMIT 5
    ");
    $recent_stmt->bind_param("i", $user_id);
    $recent_stmt->execute();
    $recent_requests = $recent_stmt->get_result();
    
} elseif ($user_role == "tutor") {
    // Статистика для фрілансера
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN status = 'Очікує' THEN 1 ELSE 0 END) as pending_requests,
            SUM(CASE WHEN status = 'Прийнята' THEN 1 ELSE 0 END) as accepted_requests,
            AVG(r.rating) as avg_rating,
            COUNT(DISTINCT r.id) as total_ratings
        FROM lesson_requests lr
        LEFT JOIN ratings r ON lr.tutor_id = r.tutor_id
        WHERE lr.tutor_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    // Отримуємо останні заявки
    $recent_stmt = $conn->prepare("
        SELECT lr.*, u.name as student_name, u.email as student_email
        FROM lesson_requests lr
        JOIN users u ON lr.student_id = u.id
        WHERE lr.tutor_id = ?
        ORDER BY lr.created_at DESC
        LIMIT 5
    ");
    $recent_stmt->bind_param("i", $user_id);
    $recent_stmt->execute();
    $recent_requests = $recent_stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель управління - Платформа клієнтів та фрілансерів</title>

    <link rel="stylesheet" href="css/modal_reviews.css">
    <link rel="stylesheet" href="css/dashboard_style.css">
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="welcome-section">
                <div class="user-avatar">
                    <?= $user_role == 'student' ? '🧑' : '👨‍💻' ?>
                </div>
                <div class="welcome-text">
                    <h2>Привіт, <?= htmlspecialchars($user_name) ?>!</h2>
                    <span class="role-badge">
                        <?= $user_role == 'student' ? '🧑 Клієнт' : '👨‍💻 Фрілансер' ?>
                    </span>
                </div>
            </div>
            <div class="header-actions">
                <?php if ($user_role == "student"): ?>
                    <a href="tutors.php" class="btn btn-primary">
                        🔍 Знайти фрілансера
                    </a>
                <?php elseif ($user_role == "tutor"): ?>
                    <a href="tutor_dashboard.php" class="btn btn-primary">
                        📋 Переглянути заявки
                    </a>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-outline">
                    🚪 Вийти
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Статистика -->
        <div class="dashboard-grid">
            <?php if ($user_role == "student"): ?>
                
            <?php else: ?>
                <div class="stat-card" onclick="showTutorReviews(<?= $user_id ?>)" style="cursor: pointer;">
                    <div class="stat-header">
                        <div class="stat-icon">⭐️</div>
                    </div>
                    <div class="stat-number">
                        <?= $stats['avg_rating'] ? number_format($stats['avg_rating'], 1) : '—' ?>
                    </div>
                    <div class="stat-label">Середній рейтинг</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Швидкі дії -->
        <div class="section">
            <div class="section-header">
                <h3 class="section-title">Швидкі дії</h3>
            </div>
            <div class="quick-actions">
                <?php if ($user_role == "student"): ?>
                    <div onclick="window.location.href='tutors.php'" class="action-card">
                        <span class="action-icon">🔍</span>
                        <div class="action-title">Пошук фрілансерів</div>
                        <div class="action-desc">Знайдіть ідеального фрілансера для ваших потреб</div>
                        <a href="tutors.php" class="action-link">Переглянути фрілансерів →</a>
                    </div>
                    <div onclick="window.location.href='all_chats.php'" class="action-card">
                        <span class="action-icon">💬</span>
                        <div class="action-title">Мої чати</div>
                        <div class="action-desc">Спілкуйтесь з фрілансерами в режимі реального часу</div>
                        <a href="all_chats.php" class="action-link">Відкрити чати →</a>
                    </div>
                    <div onclick="window.location.href='student_dashboard.php'" class="action-card">
                        <span class="action-icon">📊</span>
                        <div class="action-title">Мої заявки</div>
                        <div class="action-desc">Переглядайте статус ваших заявок на співпрацю</div>
                        <a href="student_dashboard.php" class="action-link">Переглянути заявки →</a>
                    </div>
                <?php else: ?>
                    <div onclick="window.location.href='tutor_dashboard.php'" class="action-card">
                        <span class="action-icon">📋</span>
                        <div class="action-title">Нові заявки</div>
                        <div class="action-desc">Переглядайте та обробляйте заявки від клієнтів</div>
                        <a href="tutor_dashboard.php" class="action-link">Переглянути заявки →</a>
                    </div>
                    <div onclick="window.location.href='all_chats.php'" class="action-card">
                        <span class="action-icon">💬</span>
                        <div class="action-title">Чати з клієнтами</div>
                        <div class="action-desc">Спілкуйтесь з вашими клієнтами</div>
                        <a href="all_chats.php" class="action-link">Відкрити чати →</a>
                    </div>
                    <div onclick="window.location.href='tutor_specializations.php'" class="action-card">
                        <span class="action-icon">⚙️</span>
                        <div class="action-title">Налаштування профілю</div>
                        <div class="action-desc">Оновіть інформацію про себе та свої послуги</div>
                        <a href="tutor_specializations.php" class="action-link">Редагувати профіль →</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Остання активність -->
        <div class="section">
            <div class="section-header">
                <h3 class="section-title">Остання активність</h3>
            </div>
            <div class="recent-activity">
                <?php if ($recent_requests && $recent_requests->num_rows > 0): ?>
                    <?php while ($request = $recent_requests->fetch_assoc()): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <?php
                                switch($request['status']) {
                                    case 'Очікує': echo '⏳'; break;
                                    case 'Прийнята': echo '✅'; break;
                                    case 'Відхилена': echo '❌'; break;
                                    default: echo '📧';
                                }
                                ?>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">
                                    <?php if ($user_role == "student"): ?>
                                        Заявка до <?= htmlspecialchars($request['tutor_name']) ?>
                                    <?php else: ?>
                                        Заявка від <?= htmlspecialchars($request['student_name']) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="activity-desc">
                                    <span class="status-badge status-<?= strtolower($request['status']) ?>">
                                        <?= htmlspecialchars($request['status']) ?>
                                    </span>
                                    <?php if (!empty($request['message'])): ?>
                                        • <?= htmlspecialchars(substr($request['message'], 0, 50)) ?>...
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="activity-time">
                                <?= date('d.m.Y H:i', strtotime($request['created_at'])) ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">📭</div>
                        <h4>Поки що немає активності</h4>
                        <p>
                            <?php if ($user_role == "student"): ?>
                                Почніть з пошуку фрілансера та надішліть першу заявку!
                            <?php else: ?>
                                Очікуйте на заявки від клієнтів.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="js/dashboard.js"></script>
    <script src="js/get_reviews.js"></script>
</body>
</html>