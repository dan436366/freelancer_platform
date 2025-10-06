<?php
require 'db.php';
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "student") {
    header("Location: login.php");
    exit();
}

// Отримуємо всі спеціалізації для фільтра
$specializations_query = $conn->query("SELECT DISTINCT name, icon FROM specializations ORDER BY name");
$all_specializations = $specializations_query->fetch_all(MYSQLI_ASSOC);

// Отримуємо фрілансерів з їх рейтингами, кількістю активних заявок та спеціалізаціями
$result = $conn->query("
    SELECT u.id, u.name, u.email, u.bio, u.phone, u.created_at,
           AVG(r.rating) as avg_rating, 
           COUNT(DISTINCT r.id) as rating_count,
           COUNT(DISTINCT lr.id) as active_requests
    FROM users u
    LEFT JOIN ratings r ON u.id = r.tutor_id
    LEFT JOIN lesson_requests lr ON u.id = lr.tutor_id AND lr.status = 'Прийнята'
    WHERE u.role = 'tutor'
    GROUP BY u.id, u.name, u.email, u.bio, u.phone, u.created_at
    ORDER BY avg_rating DESC, rating_count DESC
");

// Перевіряємо, чи клієнт вже надсилав заявку
$student_id = $_SESSION["user_id"];
$sent_requests = [];
$requests_stmt = $conn->prepare("SELECT tutor_id, status FROM lesson_requests WHERE student_id = ?");
$requests_stmt->bind_param("i", $student_id);
$requests_stmt->execute();
$requests_result = $requests_stmt->get_result();
while ($row = $requests_result->fetch_assoc()) {
    $sent_requests[$row['tutor_id']] = $row['status'];
}

// Функція для отримання спеціалізацій фрілансера
function getTutorSpecializations($conn, $tutor_id) {
    $stmt = $conn->prepare("
        SELECT s.name, s.icon, ts.experience_years, ts.price_per_hour, ts.description
        FROM tutor_specializations ts
        JOIN specializations s ON ts.specialization_id = s.id
        WHERE ts.tutor_id = ?
        ORDER BY s.name
    ");
    $stmt->bind_param("i", $tutor_id);
    $stmt->execute();
    return $stmt->get_result();
}

function getTutorReviews($conn, $tutor_id) {
    $stmt = $conn->prepare("
        SELECT r.rating, r.comment, r.created_at, u.name as student_name
        FROM ratings r
        JOIN users u ON r.student_id = u.id
        WHERE r.tutor_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->bind_param("i", $tutor_id);
    $stmt->execute();
    return $stmt->get_result();
}

function formatDateUkrainian($date) {
    $months = [
        'Jan' => 'Січ', 'Feb' => 'Лют', 'Mar' => 'Бер', 'Apr' => 'Кві',
        'May' => 'Тра', 'Jun' => 'Чер', 'Jul' => 'Лип', 'Aug' => 'Сер',
        'Sep' => 'Вер', 'Oct' => 'Жов', 'Nov' => 'Лис', 'Dec' => 'Гру'
    ];
    $month_en = date('M', strtotime($date));
    $year = date('Y', strtotime($date));
    return $months[$month_en] . ' ' . $year;
}

?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Пошук фрілансерів</title>
    <link rel="stylesheet" href="css/tutors_style.css">
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="header-title">
                <div class="header-icon">🔍</div>
                <div class="title-text">
                    <h1>Пошук фрілансерів</h1>
                    <div class="title-desc">Знайдіть ідеального фрілансера для ваших потреб</div>
                </div>
            </div>
            <div class="header-actions">
                <a href="dashboard.php" class="btn btn-outline">
                    ← Головна
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="search-section fade-in">
            <div class="search-header">
                <h3 class="section-title">
                    🎯 Фільтри пошуку
                </h3>
            </div>
            <div class="filter-group">
                <input type="text" id="nameFilter" class="filter-input" placeholder="🔍 Пошук за ім'ям фрілансера...">
                <select id="ratingFilter" class="filter-input">
                    <option value="">⭐ Всі рейтинги</option>
                    <option value="4">⭐ 4+ зірки</option>
                    <option value="3">⭐ 3+ зірки</option>
                    <option value="2">⭐ 2+ зірки</option>
                    <option value="1">⭐ 1+ зірки</option>
                </select>
                <select id="subjectFilter" class="filter-input">
                    <option value="">📚 Всі спеціалізації</option>
                    <?php foreach ($all_specializations as $spec): ?>
                        <option value="<?= strtolower($spec['name']) ?>">
                            <?= $spec['icon'] ?> <?= htmlspecialchars($spec['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button onclick="clearFilters()" class="btn btn-secondary">
                    🗑️ Очистити
                </button>
            </div>
        </div>

        <?php if ($result->num_rows > 0): ?>
            <div class="results-header fade-in">
                <div class="results-count">
                    Знайдено <strong><?= $result->num_rows ?></strong> фрілансерів
                </div>
            </div>

            <div class="tutors-grid" id="tutorsGrid">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php 
                    $specializations = getTutorSpecializations($conn, $row['id']);
                    $spec_array = $specializations->fetch_all(MYSQLI_ASSOC);
                    $spec_names = array_map(function($s) { return strtolower($s['name']); }, $spec_array);
                    ?>
                    <div class="tutor-card" 
                        data-name="<?= strtolower(htmlspecialchars($row['name'])) ?>" 
                        data-rating="<?= $row['avg_rating'] ?: 0 ?>" 
                        data-subjects="<?= implode(' ', $spec_names) ?>">
                        <div class="tutor-header">
                            <div>
                                <h3 class="tutor-name"><?= htmlspecialchars($row['name']) ?></h3>
                                <div class="tutor-email">
                                    📧 <?= htmlspecialchars($row['email']) ?>
                                </div>
                                <?php if ($row['phone']): ?>
                                    <div class="tutor-email">
                                        📞 <?= htmlspecialchars($row['phone']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="tutor-rating">
                                <?php if ($row['rating_count'] > 0): ?>
                                    <span class="rating-stars">
                                        <?php
                                        $rating = round($row['avg_rating']);
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo $i <= $rating ? '⭐' : '☆';
                                        }
                                        ?>
                                    </span>
                                    <span class="rating-text"><?= number_format($row['avg_rating'], 1) ?></span>
                                <?php else: ?>
                                    <span class="no-rating">Немає оцінок</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($row['bio']): ?>
                            <div class="tutor-bio"><?= htmlspecialchars($row['bio']) ?></div>
                        <?php endif; ?>

                        <?php if (!empty($spec_array)): ?>
                            <div class="specializations-section">
                                <div class="specializations-title">📚 Спеціалізації:</div>
                                <div class="specializations-list">
                                    <?php foreach ($spec_array as $spec): ?>
                                        <div class="specialization-tag">
                                            <span><?= $spec['icon'] ?> <?= htmlspecialchars($spec['name']) ?></span>
                                            <span class="spec-price"><?= number_format($spec['price_per_hour'], 0) ?> ₴/год</span>
                                            <span class="spec-experience">(<?= $spec['experience_years'] ?>р. досв.)</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="tutor-stats">
                            <div class="stat">
                                <span class="stat-number"><?= $row['rating_count'] ?></span>
                                <span class="stat-label">відгуків</span>
                            </div>
                            <div class="stat">
                                <span class="stat-number"><?= $row['active_requests'] ?></span>
                                <span class="stat-label">клієнтів</span>
                            </div>
                            <div class="stat">
                                <!-- <span class="stat-number"><?= date('M Y', strtotime($row['created_at'])) ?></span> -->
                                 <span class="stat-number"><?= formatDateUkrainian($row['created_at']) ?></span>
                                <span class="stat-label">на платформі</span>
                            </div>
                        </div>

                        <div class="tutor-actions">
                            <?php 
                            $tutor_id = $row['id'];
                            if (isset($sent_requests[$tutor_id])): 
                                $status = $sent_requests[$tutor_id];
                            ?>
                                <span class="status-badge status-<?= strtolower(str_replace(['Очікує', 'Прийнята', 'Відхилена'], ['pending', 'accepted', 'rejected'], $status)) ?>">
                                    <?php
                                    switch($status) {
                                        case 'Очікує': echo '⏳ Заявка надіслана'; break;
                                        case 'Прийнята': echo '✅ Заявка прийнята'; break;
                                        case 'Відхилена': echo '❌ Заявка відхилена'; break;
                                    }
                                    ?>
                                </span>
                                <?php if ($status === 'Прийнята'): ?>
                                    <?php
                                    $chat_stmt = $conn->prepare("SELECT id FROM lesson_requests WHERE student_id = ? AND tutor_id = ? AND status = 'Прийнята'");
                                    $chat_stmt->bind_param("ii", $student_id, $tutor_id);
                                    $chat_stmt->execute();
                                    $chat_result = $chat_stmt->get_result()->fetch_assoc();
                                    ?>
                                    <?php if ($chat_result): ?>
                                        <a href="chat.php?request_id=<?= $chat_result['id'] ?>" class="btn btn-success">
                                            💬 Чат
                                        </a>
                                    <?php endif; ?>
                                <?php elseif ($status === 'Відхилена'): ?>
                                    <form method="post" action="request_lesson.php" style="display: inline;">
                                        <input type="hidden" name="tutor_id" value="<?= $tutor_id ?>">
                                        <button type="submit" class="btn btn-primary" onclick="return confirm('Надіслати нову заявку?')">
                                            🔄 Надіслати знову
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <form method="post" action="request_lesson.php" style="display: inline;">
                                    <input type="hidden" name="tutor_id" value="<?= $tutor_id ?>">
                                    <button type="submit" class="btn btn-primary">
                                        📧 Надіслати заявку
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <button onclick="showTutorReviews(<?= $tutor_id ?>)" class="btn btn-warning">
                                💬 Відгуки
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state fade-in">
                <div class="empty-icon">👨‍💻</div>
                <h3>фрілансерів не знайдено</h3>
                <p>На платформі поки що немає зареєстрованих фрілансерів або вони не відповідають вашим критеріям пошуку.</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="js/tutors.js"></script>
    <script src="js/get_reviews.js"></script>
</body>
</html>