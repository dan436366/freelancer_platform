<?php
require 'db.php';
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "student") {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["tutor_id"])) {
    $student_id = $_SESSION["user_id"];
    $tutor_id = $_POST["tutor_id"];

    $stmt = $conn->prepare("INSERT INTO lesson_requests (student_id, tutor_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $student_id, $tutor_id);
    $stmt->execute();
    $stmt->close();
}

$user_name = $_SESSION["user_name"];
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заявка відправлена</title>
    <link rel="stylesheet" href="css/request_lesson_style.css">
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="welcome-section">
                <div class="user-avatar">👨‍🎓</div>
                <div class="welcome-text">
                    <h2>Привіт, <?= htmlspecialchars($user_name) ?>!</h2>
                    <span class="role-badge">👨‍🎓 Клієнт</span>
                </div>
            </div>
            <div class="header-actions">
                <a href="tutors.php" class="btn btn-primary">
                    🔍 Знайти фрілансера
                </a>
                <a href="logout.php" class="btn btn-outline">
                    🚪 Вийти
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="message-card">
            <div class="success-icon">✅</div>
            <h2 class="message-title">Заявка успішно відправлена!</h2>
            <p class="message-text">
                Ваша заявка була надіслана фрілансеру. Очікуйте на відповідь в найближчий час.
            </p>
            <a href="tutors.php" class="btn-back">
                ← Повернутись
            </a>
        </div>
    </div>

    <script src="js/request_lesson.js"></script>
</body>
</html>