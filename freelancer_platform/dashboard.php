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

// Перевіряємо чи користувач заблокований
$stmt = $conn->prepare("SELECT blocked, block_reason FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_status = $stmt->get_result()->fetch_assoc();

// Перевіряємо непрочитані повідомлення від модераторів
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM moderator_messages WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unread_moderator_messages = $stmt->get_result()->fetch_assoc()['count'];

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
    
    <style>
        /* Стилі для повідомлень про блокування */
        .blocked-alert {
            background: #fee2e2;
            border: 3px solid #ef4444;
            padding: 20px;
            margin: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        
        .blocked-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .blocked-icon {
            font-size: 48px;
        }
        
        .blocked-title h2 {
            margin: 0;
            color: #991b1b;
            font-size: 24px;
        }
        
        .blocked-title p {
            margin: 5px 0 0 0;
            color: #7f1d1d;
            font-weight: 500;
        }
        
        .blocked-reason {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .blocked-reason strong {
            color: #991b1b;
            display: block;
            margin-bottom: 10px;
        }
        
        .blocked-reason p {
            margin: 0;
            color: #333;
            line-height: 1.6;
        }
        
        .blocked-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .moderator-link {
            background: #dc2626;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s;
        }
        
        .moderator-link:hover {
            background: #b91c1c;
        }
        
        .unread-badge {
            background: #fbbf24;
            color: #78350f;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: bold;
        }
        
        .moderator-message-alert {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            padding: 15px;
            margin: 20px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .message-alert-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message-alert-icon {
            font-size: 24px;
        }
        
        .message-alert-text {
            color: #92400e;
            font-weight: 500;
        }
        
        .view-messages-btn {
            background: #f59e0b;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.3s;
        }
        
        .view-messages-btn:hover {
            background: #d97706;
        }
        
        /* Блокуємо всі інтерактивні елементи для заблокованих користувачів */
        <?php if ($user_status['blocked']): ?>
        body form:not([action="logout.php"]) button,
        body form:not([action="logout.php"]) input[type="submit"],
        body a:not([href="contact_moderator.php"]):not([href="logout.php"]):not([href="index.php"]) {
            pointer-events: none;
            opacity: 0.5;
            cursor: not-allowed;
        }
        <?php endif; ?>
    </style>
</head>
<body>
    <!-- Повідомлення про блокування -->
    <?php if ($user_status['blocked']): ?>
    <div class="blocked-alert">
        <div class="blocked-header">
            <div class="blocked-icon">🚫</div>
            <div class="blocked-title">
                <h2>Ваш акаунт заблоковано!</h2>
                <p>Доступ до функцій платформи обмежено</p>
            </div>
        </div>
        
        <div class="blocked-reason">
            <strong>Причина блокування:</strong>
            <p><?= nl2br(htmlspecialchars($user_status['block_reason'])) ?></p>
        </div>
        
        <div class="blocked-actions">
            <a href="contact_moderator.php" class="moderator-link">
                👮 Чат з модератором
            </a>
            <?php if ($unread_moderator_messages > 0): ?>
                <span class="unread-badge">
                    📬 <?= $unread_moderator_messages ?> нових повідомлень
                </span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Сповіщення про непрочитані повідомлення від модераторів (для не заблокованих) -->
    <?php if (!$user_status['blocked'] && $unread_moderator_messages > 0): ?>
    <div class="moderator-message-alert">
        <div class="message-alert-content">
            <span class="message-alert-icon">📬</span>
            <span class="message-alert-text">
                У вас є <?= $unread_moderator_messages ?> нових повідомлень від модератора
            </span>
        </div>
        <a href="contact_moderator.php" class="view-messages-btn">
            Переглянути
        </a>
    </div>
    <?php endif; ?>

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
                    <div onclick="window.location.href='contact_moderator.php'" class="action-card" style="border: 2px solid #f59e0b;">
                        <span class="action-icon">👮</span>
                        <div class="action-title">Зв'язок з модератором
                            <?php if ($unread_moderator_messages > 0): ?>
                                <span style="background: #ef4444; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 5px;">
                                    <?= $unread_moderator_messages ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="action-desc">Напишіть модератору, якщо у вас виникли питання</div>
                        <a href="contact_moderator.php" class="action-link">Відкрити чат →</a>
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
                    <div onclick="window.location.href='contact_moderator.php'" class="action-card" style="border: 2px solid #f59e0b;">
                        <span class="action-icon">👮</span>
                        <div class="action-title">Зв'язок з модератором
                            <?php if ($unread_moderator_messages > 0): ?>
                                <span style="background: #ef4444; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 5px;">
                                    <?= $unread_moderator_messages ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="action-desc">Напишіть модератору, якщо у вас виникли питання</div>
                        <a href="contact_moderator.php" class="action-link">Відкрити чат →</a>
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

    <script>
    <?php if ($user_status['blocked']): ?>
    // Блокуємо взаємодію з формами для заблокованих користувачів
    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('form:not([action="logout.php"])');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                alert('⚠️ Ваш акаунт заблоковано. Для вирішення питання зв\'яжіться з модератором.');
                return false;
            });
        });
        
        // Блокуємо кліки по action-card
        const actionCards = document.querySelectorAll('.action-card');
        actionCards.forEach(card => {
            card.style.opacity = '0.5';
            card.style.cursor = 'not-allowed';
            card.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                alert('⚠️ Ваш акаунт заблоковано. Для вирішення питання зв\'яжіться з модератором.');
                return false;
            };
        });
    });
    <?php endif; ?>
    </script>

    <script src="js/dashboard.js"></script>
    <script src="js/get_reviews.js"></script>
</body>
</html>