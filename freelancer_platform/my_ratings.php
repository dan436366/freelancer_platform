<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['user_id'];

// Отримуємо всі відгуки клієнта з інформацією про заявки
$stmt = $conn->prepare("
    SELECT r.*, u.name as tutor_name, u.email as tutor_email, lr.id as request_id
    FROM ratings r
    JOIN users u ON r.tutor_id = u.id
    LEFT JOIN lesson_requests lr ON r.tutor_id = lr.tutor_id AND r.student_id = lr.student_id AND lr.status = 'Прийнята'
    WHERE r.student_id = ?
    ORDER BY r.created_at DESC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$ratings = $stmt->get_result();

// Статистика відгуків
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_ratings,
        AVG(rating) as avg_rating,
        COUNT(CASE WHEN comment IS NOT NULL AND comment != '' THEN 1 END) as ratings_with_comments
    FROM ratings 
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
    <title>Мої відгуки</title>
    <link rel="stylesheet" href="css/my_ratings_style.css">
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="welcome-section">
                <div class="user-avatar">📝</div>
                <div class="welcome-text">
                    <h1>Мої відгуки</h1>
                    <span class="role-badge">
                        Ваші оцінки фрілансерів
                    </span>
                </div>
            </div>
            <div class="header-actions">
                <a href="student_dashboard.php" class="btn btn-primary">
                    🏠 Повернутись
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="stats-section">
            <div class="stats-header">
                <h2 class="stats-title">
                    📊 Статистика ваших відгуків
                </h2>
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['total_ratings'] ?></div>
                    <div class="stat-label">Всього відгуків</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['total_ratings'] > 0 ? number_format($stats['avg_rating'], 1) : '0' ?></div>
                    <div class="stat-label">Середня оцінка</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['ratings_with_comments'] ?></div>
                    <div class="stat-label">З коментарями</div>
                </div>
            </div>
        </div>

        <div class="ratings-section">
            <div class="section-header">
                <h2 class="section-title">
                    ⭐ Ваші відгуки про фрілансерів
                </h2>
            </div>

            <div class="ratings-container">
                <?php if ($ratings->num_rows > 0): ?>
                    <?php while ($rating = $ratings->fetch_assoc()): ?>
                        <div class="rating-card">
                            <div class="rating-header">
                                <div class="tutor-info">
                                    <div class="tutor-avatar">👨‍💻</div>
                                    <div class="tutor-details">
                                        <h3><?= htmlspecialchars($rating['tutor_name']) ?></h3>
                                        <div class="tutor-email"><?= htmlspecialchars($rating['tutor_email']) ?></div>
                                    </div>
                                </div>
                                <div class="rating-display">
                                    <span class="stars">
                                        <?php
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo $i <= $rating['rating'] ? '⭐' : '☆';
                                        }
                                        ?>
                                    </span>
                                    <span class="rating-number"><?= $rating['rating'] ?>/5</span>
                                </div>
                            </div>

                            <?php if (!empty($rating['comment'])): ?>
                                <div class="rating-comment">
                                    <span class="comment-label">Ваш коментар:</span>
                                    <div class="comment-text"><?= nl2br(htmlspecialchars($rating['comment'])) ?></div>
                                </div>
                            <?php endif; ?>

                            <div class="rating-meta">
                                <div class="rating-date">
                                    🕐 Залишено: <?= date('d.m.Y H:i', strtotime($rating['created_at'])) ?>
                                </div>
                                <div class="actions">
                                    <?php if ($rating['request_id']): ?>
                                        <a href="rate_tutor.php?request_id=<?= $rating['request_id'] ?>" class="btn-sm btn-edit">
                                            ✏️ Редагувати
                                        </a>
                                    <?php endif; ?>
                                    <button onclick="deleteRating(<?= $rating['id'] ?>)" class="btn-sm btn-delete">
                                        🗑️ Видалити
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-ratings">
                        <div class="no-ratings-icon">📝</div>
                        <h3>У вас поки що немає відгуків</h3>
                        <p>Після взаємодії з фрілансерами ви зможете залишити відгуки.</p>
                        <p>Ваші відгуки допомагають іншим клієнтам вибрати найкращих фрілансерів!</p>
                        <p>
                            <a href="student_dashboard.php">Перейти до заявок</a> або 
                            <a href="tutors.php">Знайти фрілансера</a>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="js/my_ratings.js"></script>
</body>
</html>