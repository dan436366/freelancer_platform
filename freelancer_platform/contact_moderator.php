<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Перевіряємо чи користувач заблокований
$stmt = $conn->prepare("SELECT blocked, block_reason FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

$success = '';
$error = '';

// Обробка форми
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = htmlspecialchars(trim($_POST['message']));
    
    if (empty($message)) {
        $error = 'Будь ласка, введіть повідомлення.';
    } else {
        // Знаходимо будь-якого модератора (можна вибрати випадкового або конкретного)
        $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'moderator' LIMIT 1");
        $stmt->execute();
        $moderator = $stmt->get_result()->fetch_assoc();
        
        if ($moderator) {
            $moderator_id = $moderator['id'];
            $stmt = $conn->prepare("INSERT INTO moderator_messages (user_id, moderator_id, message) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $user_id, $moderator_id, $message);
            
            if ($stmt->execute()) {
                $success = 'Повідомлення успішно надіслано модератору!';
            } else {
                $error = 'Помилка при відправці повідомлення.';
            }
        } else {
            $error = 'На даний момент модератори недоступні.';
        }
    }
}

// Отримуємо повідомлення від модераторів
$stmt = $conn->prepare("
    SELECT mm.*, m.name as moderator_name 
    FROM moderator_messages mm
    JOIN users m ON mm.moderator_id = m.id
    WHERE mm.user_id = ?
    ORDER BY mm.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$messages = $stmt->get_result();

// Позначаємо всі повідомлення як прочитані
$stmt = $conn->prepare("UPDATE moderator_messages SET is_read = 1 WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Зв'язок з модератором</title>
    <link rel="stylesheet" href="css/login_style.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 12px 12px 0 0;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            margin: 0 0 10px 0;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .header p {
            margin: 0;
            color: #666;
        }
        
        <?php if ($user_data['blocked']): ?>
        .blocked-alert {
            background: #fee2e2;
            border: 2px solid #ef4444;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .blocked-alert h3 {
            margin: 0 0 10px 0;
            color: #991b1b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .blocked-alert p {
            margin: 0;
            color: #7f1d1d;
            line-height: 1.6;
        }
        <?php endif; ?>
        
        .content {
            background: white;
            padding: 20px;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .messages-section {
            margin-bottom: 30px;
        }
        
        .messages-section h2 {
            margin: 0 0 20px 0;
            color: #333;
            font-size: 20px;
        }
        
        .message-card {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #3b82f6;
        }
        
        .message-card.from-moderator {
            border-left-color: #f59e0b;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .message-from {
            font-weight: bold;
            color: #333;
        }
        
        .message-date {
            font-size: 12px;
            color: #666;
        }
        
        .message-text {
            color: #333;
            line-height: 1.6;
            white-space: pre-wrap;
        }
        
        .form-section h2 {
            margin: 0 0 20px 0;
            color: #333;
            font-size: 20px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
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
            min-height: 120px;
        }
        
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
        }
        
        .btn-secondary {
            background: #e5e7eb;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #d1d5db;
        }
        
        .empty-messages {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .empty-messages-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👮 Зв'язок з модератором</h1>
            <p>Напишіть нам, якщо у вас виникли питання або проблеми</p>
        </div>
        
        <div class="content">
            <?php if ($user_data['blocked']): ?>
                <div class="blocked-alert">
                    <h3>🚫 Ваш акаунт заблоковано</h3>
                    <p><?= nl2br(htmlspecialchars($user_data['block_reason'])) ?></p>
                    <p style="margin-top: 10px;"><strong>Напишіть модератору нижче, щоб вирішити це питання.</strong></p>
                </div>
            <?php endif; ?>
            
            <?php if ($messages->num_rows > 0): ?>
                <div class="messages-section">
                    <h2>📬 Повідомлення від модераторів</h2>
                    <?php while ($msg = $messages->fetch_assoc()): ?>
                        <div class="message-card from-moderator">
                            <div class="message-header">
                                <span class="message-from">👮 <?= htmlspecialchars($msg['moderator_name']) ?></span>
                                <span class="message-date"><?= date('d.m.Y H:i', strtotime($msg['created_at'])) ?></span>
                            </div>
                            <div class="message-text"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
            
            <div class="form-section">
                <h2>✉️ Написати модератору</h2>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        ✅ <?= $success ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-error">
                        ⚠️ <?= $error ?>
                    </div>
                <?php endif; ?>
                
                <form method="post">
                    <div class="form-group">
                        <label for="message">Ваше повідомлення:</label>
                        <textarea 
                            name="message" 
                            id="message" 
                            required
                            placeholder="Опишіть вашу ситуацію або питання..."
                        ><?= isset($_POST['message']) && !$success ? htmlspecialchars($_POST['message']) : '' ?></textarea>
                    </div>
                    
                    <div class="buttons">
                        <a href="dashboard.php" class="btn btn-secondary">← Назад</a>
                        <button type="submit" class="btn btn-primary">📤 Надіслати</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>