<?php
require 'db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] != "student" && $_SESSION["role"] != "tutor")) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['tutor_id'])) {
    echo json_encode(['error' => 'Missing tutor_id']);
    exit();
}

$tutor_id = intval($_GET['tutor_id']);

// фрілансер може переглядати тільки свої відгуки
if ($_SESSION["role"] == "tutor" && $tutor_id != $_SESSION["user_id"]) {
    echo json_encode(['error' => 'Forbidden']);
    exit();
}

$tutor_id = intval($_GET['tutor_id']);


$tutor_stmt = $conn->prepare("SELECT name FROM users WHERE id = ? AND role = 'tutor'");
$tutor_stmt->bind_param("i", $tutor_id);
$tutor_stmt->execute();
$tutor_result = $tutor_stmt->get_result();
$tutor = $tutor_result->fetch_assoc();

if (!$tutor) {
    echo json_encode(['error' => 'Tutor not found']);
    exit();
}

// Отримуємо відгуки
$reviews_stmt = $conn->prepare("
    SELECT r.rating, r.comment, r.created_at, u.name as student_name
    FROM ratings r
    JOIN users u ON r.student_id = u.id
    WHERE r.tutor_id = ?
    ORDER BY r.created_at DESC
");
$reviews_stmt->bind_param("i", $tutor_id);
$reviews_stmt->execute();
$reviews_result = $reviews_stmt->get_result();

$reviews = [];
while ($row = $reviews_result->fetch_assoc()) {
    $reviews[] = [
        'rating' => intval($row['rating']),
        'comment' => $row['comment'],
        'created_at' => $row['created_at'],
        'student_name' => $row['student_name']
    ];
}

echo json_encode([
    'tutor_name' => $tutor['name'],
    'reviews' => $reviews
]);
?>