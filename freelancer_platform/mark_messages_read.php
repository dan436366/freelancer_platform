<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit();
}

$user_id = $_SESSION['user_id'];
$request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;

if ($request_id <= 0) {
    http_response_code(400);
    exit();
}

// Перевіряємо, чи користувач має доступ до заявки
$stmt = $conn->prepare("SELECT * FROM lesson_requests WHERE id = ? AND (student_id = ? OR tutor_id = ?)");
$stmt->bind_param("iii", $request_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);
    exit();
}

// Позначаємо всі повідомлення від інших користувачів як прочитані
$stmt = $conn->prepare("UPDATE messages SET seen = 1 WHERE request_id = ? AND sender_id != ? AND seen = 0");
$stmt->bind_param("ii", $request_id, $user_id);
$stmt->execute();

echo "OK";
?>