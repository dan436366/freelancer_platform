<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rating_id'])) {
    $rating_id = intval($_POST['rating_id']);
    $student_id = $_SESSION['user_id'];
    
    // Перевіряємо, що відгук належить цьому клієнту
    $stmt = $conn->prepare("SELECT id FROM ratings WHERE id = ? AND student_id = ?");
    $stmt->bind_param("ii", $rating_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $delete_stmt = $conn->prepare("DELETE FROM ratings WHERE id = ? AND student_id = ?");
        $delete_stmt->bind_param("ii", $rating_id, $student_id);
        
        if ($delete_stmt->execute()) {
            $_SESSION['success_message'] = 'Відгук успішно видалено.';
        } else {
            $_SESSION['error_message'] = 'Помилка при видаленні відгуку.';
        }
    } else {
        $_SESSION['error_message'] = 'Відгук не знайдено або ви не маєте права його видаляти.';
    }
} else {
    $_SESSION['error_message'] = 'Невірний запит.';
}

header('Location: my_ratings.php');
exit();
?>