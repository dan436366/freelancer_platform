<?php
require 'db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tutor') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = intval($_POST['request_id']);
    $action = $_POST['action'];
    $tutor_id = $_SESSION['user_id'];

    if (!in_array($action, ['accept', 'reject'])) {
        die("Невідома дія.");
    }

    // Перевіряємо, що заявка належить цьому фрілансеру
    $stmt = $conn->prepare("SELECT id FROM lesson_requests WHERE id = ? AND tutor_id = ? AND status = 'Очікує'");
    $stmt->bind_param("ii", $request_id, $tutor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        die("Заявку не знайдено або ви не маєте права її змінювати.");
    }

    $new_status = $action === 'accept' ? 'Прийнята' : 'Відхилена';

    $stmt = $conn->prepare("UPDATE lesson_requests SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $request_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = $action === 'accept' ? 'Заявку прийнято!' : 'Заявку відхилено!';
    } else {
        $_SESSION['error_message'] = 'Помилка при оновленні заявки.';
    }

    header("Location: tutor_dashboard.php");
    exit();
}

header("Location: tutor_dashboard.php");
exit();
?>