<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;

// Перевіряємо, що заявка існує і належить клієнту, і статус прийнята
$stmt = $conn->prepare("
    SELECT lr.*, u.name as tutor_name 
    FROM lesson_requests lr
    JOIN users u ON lr.tutor_id = u.id
    WHERE lr.id = ? AND lr.student_id = ? AND lr.status = 'Прийнята'
");
$stmt->bind_param("ii", $request_id, $student_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request) {
    echo "<p>Заявку не знайдено або ви не можете її оцінити.</p>";
    echo "<a href='student_dashboard.php'>Повернутись до кабінету</a>";
    exit();
}

$tutor_id = $request['tutor_id'];

// Перевіряємо, чи вже є оцінка
$stmt = $conn->prepare("SELECT id FROM ratings WHERE student_id = ? AND tutor_id = ?");
$stmt->bind_param("ii", $student_id, $tutor_id);
$stmt->execute();
$existing_rating = $stmt->get_result()->fetch_assoc();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);
    
    if ($rating < 1 || $rating > 5) {
        $error = 'Оцінка повинна бути від 1 до 5.';
    } else {
        if ($existing_rating) {
            // Оновлюємо існуючу оцінку
            $stmt = $conn->prepare("UPDATE ratings SET rating = ?, comment = ? WHERE student_id = ? AND tutor_id = ?");
            $stmt->bind_param("isii", $rating, $comment, $student_id, $tutor_id);
        } else {
            // Додаємо нову оцінку
            $stmt = $conn->prepare("INSERT INTO ratings (student_id, tutor_id, rating, comment) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiis", $student_id, $tutor_id, $rating, $comment);
        }
        
        if ($stmt->execute()) {
            $success = $existing_rating ? 'Оцінку оновлено!' : 'Дякуємо за оцінку!';
        } else {
            $error = 'Помилка при збереженні оцінки.';
        }
    }
}

// Отримуємо поточну оцінку якщо є
if ($existing_rating) {
    $stmt = $conn->prepare("SELECT rating, comment FROM ratings WHERE student_id = ? AND tutor_id = ?");
    $stmt->bind_param("ii", $student_id, $tutor_id);
    $stmt->execute();
    $current_rating = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оцінити фрілансера</title>
    <link rel="stylesheet" href="css/rate_tutor_style.css">
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="welcome-section">
                <div class="user-avatar">⭐</div>
                <div class="welcome-text">
                    <h1>Оцінити фрілансера</h1>
                    <span class="role-badge">
                        Ваш відгук важливий для нас
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
        <?php if ($success): ?>
            <div class="alert alert-success">
                ✅ <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                ❌ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="rating-section">
            <div class="section-header">
                <h2 class="section-title">
                    ⭐ Оцініть роботу фрілансера
                </h2>
            </div>

            <div class="tutor-info">
                <div class="tutor-card">
                    <div class="tutor-avatar">👨‍💻</div>
                    <div class="tutor-details">
                        <div class="tutor-name"><?= htmlspecialchars($request['tutor_name']) ?></div>
                        <div class="tutor-meta">
                            <div>📅 Дата заявки: <?= date('d.m.Y H:i', strtotime($request['created_at'])) ?></div>
                            <?php if ($existing_rating): ?>
                                <div class="current-rating">
                                    🌟 Ваша поточна оцінка: <?= $current_rating['rating'] ?> з 5
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-container">
                <form method="post" id="ratingForm">
                    <div class="form-group">
                        <label class="form-label">Оберіть оцінку від 1 до 5 зірок:</label>
                        <div class="rating-stars" id="ratingStars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star" data-rating="<?= $i ?>">⭐</span>
                            <?php endfor; ?>
                        </div>
                        <div class="rating-text" id="ratingText">
                            <?php if (isset($current_rating)): ?>
                                Ваша оцінка: <?= $current_rating['rating'] ?> з 5
                            <?php else: ?>
                                Оберіть оцінку
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="rating" id="ratingValue" value="<?= isset($current_rating) ? $current_rating['rating'] : '' ?>">
                    </div>

                    <div class="form-group">
                        <label for="comment" class="form-label">Ваш коментар (необов'язково):</label>
                        <div class="comment-container">
                            <textarea 
                                name="comment" 
                                id="comment" 
                                class="comment-textarea"
                                placeholder="Поділіться своїм досвідом роботи з фрілансером. Що вам сподобалось? Що можна покращити?"
                            ><?= isset($current_rating) ? htmlspecialchars($current_rating['comment']) : '' ?></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-lg btn-submit" id="submitBtn" disabled>
                            <?= $existing_rating ? '🔄 Оновити оцінку' : '⭐ Залишити оцінку' ?>
                        </button>
                        <a href="my_ratings.php" class="btn-lg btn-secondary">
                            📝 Повернутись до відгуків
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="js/rate_tutor.js"></script>
</body>
</html>