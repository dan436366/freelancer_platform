<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;

// Перевіряємо доступ до заявки
$stmt = $conn->prepare("
    SELECT lr.*, 
           student.name as student_name,
           tutor.name as tutor_name
    FROM lesson_requests lr
    JOIN users student ON lr.student_id = student.id
    JOIN users tutor ON lr.tutor_id = tutor.id
    WHERE lr.id = ? AND (lr.student_id = ? OR lr.tutor_id = ?)
");
$stmt->bind_param("iii", $request_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "Немає доступу до цієї заявки.";
    exit();
}

$request_data = $result->fetch_assoc();

// Визначаємо проти кого скарга
$against_user_id = ($request_data['student_id'] == $user_id) ? $request_data['tutor_id'] : $request_data['student_id'];
$against_user_name = ($request_data['student_id'] == $user_id) ? $request_data['tutor_name'] : $request_data['student_name'];

// Перевіряємо чи вже є активна скарга
$stmt = $conn->prepare("
    SELECT * FROM complaints 
    WHERE request_id = ? AND complainant_id = ? AND status IN ('Очікує', 'В обробці')
");
$stmt->bind_param("ii", $request_id, $user_id);
$stmt->execute();
$existing_complaint = $stmt->get_result();

$error = '';
$success = '';

if ($existing_complaint->num_rows > 0) {
    $error = 'Ви вже подали скаргу по цій заявці. Дочекайтесь її розгляду.';
}

// Обробка форми
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $reason = htmlspecialchars(trim($_POST['reason']));
    
    if (empty($reason)) {
        $error = 'Будь ласка, опишіть причину скарги.';
    } else {
        $stmt = $conn->prepare("
            INSERT INTO complaints (request_id, complainant_id, against_user_id, reason) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("iiis", $request_id, $user_id, $against_user_id, $reason);
        
        if ($stmt->execute()) {
            $success = 'Скаргу успішно подано! Модератор розгляне її найближчим часом.';
        } else {
            $error = 'Помилка при поданні скарги. Спробуйте пізніше.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Подати скаргу</title>
    <link rel="stylesheet" href="css/login_style.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .complaint-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            max-width: 600px;
            width: 100%;
            padding: 30px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        
        .header h1 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .header p {
            color: #666;
            margin: 0;
        }
        
        .request-info {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #3b82f6;
        }
        
        .request-info h3 {
            margin: 0 0 10px 0;
            color: #1e40af;
            font-size: 16px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .info-label {
            color: #666;
        }
        
        .info-value {
            font-weight: bold;
            color: #333;
        }
        
        .against-user {
            background: #fef3c7;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #f59e0b;
        }
        
        .against-user h3 {
            margin: 0 0 5px 0;
            color: #92400e;
            font-size: 16px;
        }
        
        .against-user p {
            margin: 0;
            color: #78350f;
            font-size: 18px;
            font-weight: bold;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            resize: vertical;
            min-height: 150px;
            transition: border-color 0.3s;
        }
        
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 12px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
            display: inline-block;
        }
        
        .btn-primary {
            background: #ef4444;
            color: white;
        }
        
        .btn-primary:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }
        
        .btn-primary:disabled {
            background: #d1d5db;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: #e5e7eb;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #d1d5db;
        }
        
        .warning-box {
            background: #fef2f2;
            border: 2px solid #fecaca;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .warning-box h4 {
            margin: 0 0 10px 0;
            color: #991b1b;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .warning-box ul {
            margin: 0;
            padding-left: 20px;
            color: #7f1d1d;
        }
        
        .warning-box li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="complaint-container">
        <div class="header">
            <div class="header-icon">⚠️</div>
            <h1>Подати скаргу</h1>
            <p>Опишіть проблему, яка виникла</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?= $success ?>
            </div>
            <div class="buttons">
                <a href="chat.php?request_id=<?= $request_id ?>" class="btn btn-secondary">
                    ← Повернутись до чату
                </a>
                <a href="all_chats.php" class="btn btn-secondary">
                    Мої чати
                </a>
            </div>
        <?php else: ?>
            <div class="request-info">
                <h3>📋 Інформація про заявку</h3>
                <div class="info-row">
                    <span class="info-label">Номер заявки:</span>
                    <span class="info-value">#<?= $request_id ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Студент:</span>
                    <span class="info-value"><?= htmlspecialchars($request_data['student_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Репетитор:</span>
                    <span class="info-value"><?= htmlspecialchars($request_data['tutor_name']) ?></span>
                </div>
            </div>

            <div class="against-user">
                <h3>Скарга на користувача:</h3>
                <p><?= htmlspecialchars($against_user_name) ?></p>
            </div>

            <div class="warning-box">
                <h4>⚡ Важливо:</h4>
                <ul>
                    <li>Подавайте скарги лише у випадку реальних порушень</li>
                    <li>Опишіть ситуацію детально та чесно</li>
                    <li>Модератор переглянути історію вашого чату</li>
                    <li>Необґрунтовані скарги можуть призвести до санкцій</li>
                </ul>
            </div>

            <form method="post">
                <div class="form-group">
                    <label for="reason">Опишіть причину скарги:</label>
                    <textarea 
                        name="reason" 
                        id="reason" 
                        required
                        placeholder="Наприклад: Користувач використовує нецензурну лексику, вимагає додаткову оплату поза платформою, не виконує свої обов'язки..."
                    ><?= isset($_POST['reason']) ? htmlspecialchars($_POST['reason']) : '' ?></textarea>
                    <small>Мінімум 20 символів. Будьте якомога конкретнішими.</small>
                </div>

                <div class="buttons">
                    <a href="chat.php?request_id=<?= $request_id ?>" class="btn btn-secondary">
                        Скасувати
                    </a>
                    <button type="submit" class="btn btn-primary" <?= !empty($error) ? 'disabled' : '' ?>>
                        📝 Подати скаргу
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>