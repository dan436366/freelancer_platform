<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'moderator') {
    header('Location: moderator_login.php');
    exit();
}

$moderator_id = $_SESSION['user_id'];
$moderator_name = $_SESSION['user_name'];

// Обробка дій модератора
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // Обробка спеціалізацій
        if ($action === 'add_specialization') {
            $name = htmlspecialchars(trim($_POST['spec_name']));
            $description = htmlspecialchars(trim($_POST['spec_description']));
            $icon = htmlspecialchars(trim($_POST['spec_icon']));
            
            if (!empty($name)) {
                $stmt = $conn->prepare("INSERT INTO specializations (name, description, icon) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $name, $description, $icon);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'Спеціалізацію успішно додано!';
                } else {
                    $_SESSION['error_message'] = 'Помилка при додаванні спеціалізації. Можливо, вона вже існує.';
                }
            }
            header('Location: moderator_dashboard.php#specializations');
            exit();
        }
        
        if ($action === 'delete_specialization') {
            $spec_id = intval($_POST['spec_id']);
            
            // Перевіряємо чи використовується спеціалізація
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tutor_specializations WHERE specialization_id = ?");
            $stmt->bind_param("i", $spec_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if ($result['count'] > 0) {
                $_SESSION['error_message'] = 'Неможливо видалити спеціалізацію - вона використовується викладачами!';
            } else {
                $stmt = $conn->prepare("DELETE FROM specializations WHERE id = ?");
                $stmt->bind_param("i", $spec_id);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'Спеціалізацію успішно видалено!';
                } else {
                    $_SESSION['error_message'] = 'Помилка при видаленні спеціалізації.';
                }
            }
            header('Location: moderator_dashboard.php#specializations');
            exit();
        }
        
        $complaint_id = intval($_POST['complaint_id']);
        
        if ($_POST['action'] === 'take') {
            // Взяти скаргу в роботу
            $stmt = $conn->prepare("UPDATE complaints SET status = 'В обробці', moderator_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $moderator_id, $complaint_id);
            $stmt->execute();
        } elseif ($_POST['action'] === 'resolve') {
            // Вирішити скаргу
            $note = htmlspecialchars(trim($_POST['moderator_note']));
            $block_user = isset($_POST['block_user']) ? 1 : 0;
            
            // Отримуємо дані скарги
            $stmt = $conn->prepare("SELECT complainant_id, against_user_id FROM complaints WHERE id = ?");
            $stmt->bind_param("i", $complaint_id);
            $stmt->execute();
            $complaint_data = $stmt->get_result()->fetch_assoc();
            
            // Оновлюємо статус скарги
            $stmt = $conn->prepare("UPDATE complaints SET status = 'Вирішена', moderator_note = ?, user_blocked = ?, resolved_at = NOW() WHERE id = ?");
            $stmt->bind_param("sii", $note, $block_user, $complaint_id);
            $stmt->execute();
            
            // Якщо користувача потрібно заблокувати
            if ($block_user) {
                $block_reason = "Заблоковано модератором за порушення правил. Причина: " . $note;
                $stmt = $conn->prepare("UPDATE users SET blocked = 1, block_reason = ? WHERE id = ?");
                $stmt->bind_param("si", $block_reason, $complaint_data['against_user_id']);
                $stmt->execute();
                
                // Відправляємо повідомлення порушнику (ДОДАНО sender_id)
                $block_message = "Ваш акаунт заблоковано за порушення правил платформи.\n\nПричина: " . $note . "\n\nДля вирішення питання зв'яжіться з модератором.";
                $stmt = $conn->prepare("INSERT INTO moderator_messages (user_id, moderator_id, sender_id, complaint_id, message) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iiiis", $complaint_data['against_user_id'], $moderator_id, $moderator_id, $complaint_id, $block_message);
                $stmt->execute();
            }
            
            // Відправляємо повідомлення скаржнику (ДОДАНО sender_id)
            $complainant_message = "Ваша скарга #" . $complaint_id . " була розглянута.\n\nРішення модератора: " . $note;
            if ($block_user) {
                $complainant_message .= "\n\nПорушника заблоковано.";
            }
            $stmt = $conn->prepare("INSERT INTO moderator_messages (user_id, moderator_id, sender_id, complaint_id, message) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiis", $complaint_data['complainant_id'], $moderator_id, $moderator_id, $complaint_id, $complainant_message);
            $stmt->execute();
            
        } elseif ($_POST['action'] === 'reject') {
            // Відхилити скаргу
            $note = htmlspecialchars(trim($_POST['moderator_note']));
            
            // Отримуємо дані скарги
            $stmt = $conn->prepare("SELECT complainant_id FROM complaints WHERE id = ?");
            $stmt->bind_param("i", $complaint_id);
            $stmt->execute();
            $complaint_data = $stmt->get_result()->fetch_assoc();
            
            $stmt = $conn->prepare("UPDATE complaints SET status = 'Відхилена', moderator_note = ?, resolved_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $note, $complaint_id);
            $stmt->execute();
            
            // Відправляємо повідомлення скаржнику (ДОДАНО sender_id)
            $complainant_message = "Ваша скарга #" . $complaint_id . " була відхилена.\n\nПояснення модератора: " . $note;
            $stmt = $conn->prepare("INSERT INTO moderator_messages (user_id, moderator_id, sender_id, complaint_id, message) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiis", $complaint_data['complainant_id'], $moderator_id, $moderator_id, $complaint_id, $complainant_message);
            $stmt->execute();
        }
    }
}

// Отримуємо скарги
$active_filter = isset($_GET['filter']) ? $_GET['filter'] : 'active';

if ($active_filter === 'my') {
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            complainant.name as complainant_name,
            complainant.role as complainant_role,
            against.name as against_name,
            against.role as against_role,
            lr.student_id,
            lr.tutor_id
        FROM complaints c
        JOIN users complainant ON c.complainant_id = complainant.id
        JOIN users against ON c.against_user_id = against.id
        JOIN lesson_requests lr ON c.request_id = lr.id
        WHERE c.moderator_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->bind_param("i", $moderator_id);
} elseif ($active_filter === 'resolved') {
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            complainant.name as complainant_name,
            complainant.role as complainant_role,
            against.name as against_name,
            against.role as against_role,
            lr.student_id,
            lr.tutor_id,
            moderator.name as moderator_name
        FROM complaints c
        JOIN users complainant ON c.complainant_id = complainant.id
        JOIN users against ON c.against_user_id = against.id
        JOIN lesson_requests lr ON c.request_id = lr.id
        LEFT JOIN users moderator ON c.moderator_id = moderator.id
        WHERE c.status IN ('Вирішена', 'Відхилена')
        ORDER BY c.resolved_at DESC
    ");
} else {
    // Активні скарги (Очікує або В обробці)
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            complainant.name as complainant_name,
            complainant.role as complainant_role,
            against.name as against_name,
            against.role as against_role,
            lr.student_id,
            lr.tutor_id,
            moderator.name as moderator_name
        FROM complaints c
        JOIN users complainant ON c.complainant_id = complainant.id
        JOIN users against ON c.against_user_id = against.id
        JOIN lesson_requests lr ON c.request_id = lr.id
        LEFT JOIN users moderator ON c.moderator_id = moderator.id
        WHERE c.status IN ('Очікує', 'В обробці')
        ORDER BY c.created_at DESC
    ");
}

$stmt->execute();
$complaints = $stmt->get_result();

// Статистика
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM complaints WHERE status = 'Очікує'");
$stmt->execute();
$pending_count = $stmt->get_result()->fetch_assoc()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM complaints WHERE status = 'В обробці' AND moderator_id = ?");
$stmt->bind_param("i", $moderator_id);
$stmt->execute();
$my_active = $stmt->get_result()->fetch_assoc()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM complaints WHERE moderator_id = ? AND status IN ('Вирішена', 'Відхилена')");
$stmt->bind_param("i", $moderator_id);
$stmt->execute();
$my_resolved = $stmt->get_result()->fetch_assoc()['count'];

// Отримуємо всі спеціалізації
$all_specializations = $conn->query("
    SELECT s.*, 
           COUNT(ts.id) as tutor_count 
    FROM specializations s 
    LEFT JOIN tutor_specializations ts ON s.id = ts.specialization_id 
    GROUP BY s.id 
    ORDER BY s.name
");
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель модератора</title>
    <link rel="stylesheet" href="css/login_style.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .moderator-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            margin: 0;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logout-btn {
            background: #ef4444;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: #dc2626;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
            font-weight: 500;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: #333;
        }
        
        .stat-card.pending .number {
            color: #f59e0b;
        }
        
        .stat-card.active .number {
            color: #3b82f6;
        }
        
        .stat-card.resolved .number {
            color: #10b981;
        }
        
        .filters {
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            gap: 10px;
        }
        
        .filter-btn {
            padding: 8px 16px;
            border: 2px solid #e5e7eb;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: #333;
            font-size: 14px;
        }
        
        .filter-btn:hover {
            border-color: #667eea;
            color: #667eea;
        }
        
        .filter-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .complaints-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .complaint-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .complaint-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .complaint-id {
            font-size: 12px;
            color: #666;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-badge.in-progress {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-badge.resolved {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-badge.rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .complaint-users {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            padding: 10px;
            background: #f9fafb;
            border-radius: 8px;
        }
        
        .user-badge {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .role-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        
        .role-icon.student {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .role-icon.tutor {
            background: #dcfce7;
            color: #166534;
        }
        
        .complaint-reason {
            background: #fef3c7;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #f59e0b;
        }
        
        .complaint-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
        }
        
        .modal-content h3 {
            margin-top: 0;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
        }
        
        .moderator-note {
            background: #f0f9ff;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            border-left: 4px solid #3b82f6;
        }
        
        .moderator-note strong {
            display: block;
            margin-bottom: 5px;
            color: #1e40af;
        }
        
        .empty-state {
            background: white;
            padding: 60px 20px;
            border-radius: 12px;
            text-align: center;
            color: #666;
        }
        
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .specializations-section {
            margin-top: 30px;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            scroll-margin-top: 20px;
        }
        
        .specializations-section h2 {
            margin: 0 0 20px 0;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .spec-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .spec-item {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: start;
            transition: all 0.3s;
        }
        
        .spec-item:hover {
            border-color: #667eea;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
        }
        
        .spec-info {
            flex: 1;
        }
        
        .spec-name {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .spec-description {
            font-size: 13px;
            color: #666;
            margin-bottom: 8px;
        }
        
        .spec-usage {
            font-size: 12px;
            color: #10b981;
            font-weight: 500;
        }
        
        .spec-actions {
            display: flex;
            gap: 5px;
        }
        
        .btn-delete-spec {
            background: #ef4444;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }
        
        .btn-delete-spec:hover {
            background: #dc2626;
        }
        
        .btn-delete-spec:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }
        
        .add-spec-form {
            background: #f0f9ff;
            border: 2px dashed #3b82f6;
            border-radius: 12px;
            padding: 20px;
        }
        
        .add-spec-form h3 {
            margin: 0 0 15px 0;
            color: #1e40af;
        }
        
        .form-row {
            display: flex;
            grid-template-columns: 2fr 3fr 100px;
            gap: 15px;
            margin-bottom: 15px;
            justify-content: space-between;
        }
        
        .form-control {
            padding: 10px;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
        }
        
        .form-control.icon-input {
            text-align: center;
            font-size: 24px;
        }
    </style>
</head>
<body>
    <div class="moderator-container">
        <div class="header">
            <h1>👮 Панель модератора</h1>
            <div class="user-info">
                <a href="#specializations" class="nav-btn" style="background: #f59e0b; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; margin-right: 10px;">
                    📚 Спеціалізації
                </a>
                <a href="moderator_inbox.php" class="nav-btn" style="background: #10b981; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; margin-right: 10px;">
                    📬 Вхідні повідомлення
                </a>
                <span>Вітаємо, <strong><?= htmlspecialchars($moderator_name) ?></strong></span>
                <a href="logout.php" class="logout-btn">Вийти</a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card pending">
                <h3>⏳ Нові скарги</h3>
                <div class="number"><?= $pending_count ?></div>
            </div>
            <div class="stat-card active">
                <h3>🔄 Мої активні</h3>
                <div class="number"><?= $my_active ?></div>
            </div>
            <div class="stat-card resolved">
                <h3>✅ Вирішено мною</h3>
                <div class="number"><?= $my_resolved ?></div>
            </div>
        </div>

        <div class="filters">
            <a href="?filter=active" class="filter-btn <?= $active_filter === 'active' ? 'active' : '' ?>">
                Активні скарги
            </a>
            <a href="?filter=my" class="filter-btn <?= $active_filter === 'my' ? 'active' : '' ?>">
                Мої скарги
            </a>
            <a href="?filter=resolved" class="filter-btn <?= $active_filter === 'resolved' ? 'active' : '' ?>">
                Вирішені скарги
            </a>
        </div>

        <div class="complaints-list">
            <?php if ($complaints->num_rows > 0): ?>
                <?php while ($complaint = $complaints->fetch_assoc()): ?>
                    <div class="complaint-card">
                        <div class="complaint-header">
                            <div>
                                <div class="complaint-id">Скарга #<?= $complaint['id'] ?> | Заявка #<?= $complaint['request_id'] ?></div>
                                <small style="color: #666;">Створено: <?= date('d.m.Y H:i', strtotime($complaint['created_at'])) ?></small>
                            </div>
                            <span class="status-badge <?= 
                                $complaint['status'] === 'Очікує' ? 'pending' : 
                                ($complaint['status'] === 'В обробці' ? 'in-progress' : 
                                ($complaint['status'] === 'Вирішена' ? 'resolved' : 'rejected')) 
                            ?>">
                                <?= htmlspecialchars($complaint['status']) ?>
                            </span>
                        </div>

                        <div class="complaint-users">
                            <div class="user-badge">
                                <div class="role-icon <?= $complaint['complainant_role'] ?>">
                                    <?= $complaint['complainant_role'] === 'student' ? '🎓' : '👨‍🏫' ?>
                                </div>
                                <div>
                                    <strong><?= htmlspecialchars($complaint['complainant_name']) ?></strong>
                                    <div style="font-size: 12px; color: #666;">Скаржник (ID: <?= $complaint['complainant_id'] ?>)</div>
                                </div>
                            </div>
                            
                            <span style="color: #666;">→</span>
                            
                            <div class="user-badge">
                                <div class="role-icon <?= $complaint['against_role'] ?>">
                                    <?= $complaint['against_role'] === 'student' ? '🎓' : '👨‍🏫' ?>
                                </div>
                                <div>
                                    <strong><?= htmlspecialchars($complaint['against_name']) ?></strong>
                                    <div style="font-size: 12px; color: #666;">Порушник (ID: <?= $complaint['against_user_id'] ?>)</div>
                                </div>
                            </div>
                        </div>

                        <div class="complaint-reason">
                            <strong>Причина скарги:</strong>
                            <p style="margin: 5px 0 0 0;"><?= nl2br(htmlspecialchars($complaint['reason'])) ?></p>
                        </div>

                        <?php if (!empty($complaint['moderator_note'])): ?>
                            <div class="moderator-note">
                                <strong>Примітка модератора <?= isset($complaint['moderator_name']) ? '(' . htmlspecialchars($complaint['moderator_name']) . ')' : '' ?>:</strong>
                                <p style="margin: 5px 0 0 0;"><?= nl2br(htmlspecialchars($complaint['moderator_note'])) ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="complaint-actions">
                            <a href="moderator_chat.php?request_id=<?= $complaint['request_id'] ?>&complaint_id=<?= $complaint['id'] ?>" 
                               class="btn btn-secondary" target="_blank">
                                💬 Переглянути чат
                            </a>

                            <?php if ($complaint['status'] === 'Очікує'): ?>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="complaint_id" value="<?= $complaint['id'] ?>">
                                    <input type="hidden" name="action" value="take">
                                    <button type="submit" class="btn btn-primary">
                                        ✋ Взяти в роботу
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ($complaint['status'] === 'В обробці' && $complaint['moderator_id'] == $moderator_id): ?>
                                <button onclick="openModal(<?= $complaint['id'] ?>, 'resolve')" class="btn btn-success">
                                    ✅ Вирішити
                                </button>
                                <button onclick="openModal(<?= $complaint['id'] ?>, 'reject')" class="btn btn-danger">
                                    ❌ Відхилити
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🔭</div>
                    <h3>Немає скарг</h3>
                    <p>У цій категорії поки що немає скарг</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Секція управління спеціалізаціями -->
        <div id="specializations" class="specializations-section">
            <h2>📚 Управління спеціалізаціями</h2>
            
            <?php if (isset($_SESSION['success_message'])): ?>
                <div style="background: #d1fae5; color: #065f46; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #10b981;">
                    ✅ <?= htmlspecialchars($_SESSION['success_message']) ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div style="background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ef4444;">
                    ❌ <?= htmlspecialchars($_SESSION['error_message']) ?>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            
            <div class="spec-grid">
                <?php while ($spec = $all_specializations->fetch_assoc()): ?>
                    <div class="spec-item">
                        <div class="spec-info">
                            <div class="spec-name">
                                <span><?= $spec['icon'] ?></span>
                                <span><?= htmlspecialchars($spec['name']) ?></span>
                            </div>
                            <?php if (!empty($spec['description'])): ?>
                                <div class="spec-description">
                                    <?= htmlspecialchars($spec['description']) ?>
                                </div>
                            <?php endif; ?>
                            <div class="spec-usage">
                                👥 Використовується: <?= $spec['tutor_count'] ?> викладачів
                            </div>
                        </div>
                        <div class="spec-actions">
                            <form method="post" style="display: inline;" onsubmit="return confirm('Видалити спеціалізацію &quot;<?= htmlspecialchars($spec['name']) ?>&quot;?');">
                                <input type="hidden" name="action" value="delete_specialization">
                                <input type="hidden" name="spec_id" value="<?= $spec['id'] ?>">
                                <button type="submit" class="btn-delete-spec" <?= $spec['tutor_count'] > 0 ? 'disabled title="Використовується викладачами"' : '' ?>>
                                    🗑️
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <div class="add-spec-form">
                <h3>➕ Додати нову спеціалізацію</h3>
                <form method="post">
                    <input type="hidden" name="action" value="add_specialization">
                    <div class="form-row">
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-size: 12px; color: #666;">Назва *</label>
                            <input type="text" name="spec_name" class="form-control" placeholder="Назва предмету" required>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-size: 12px; color: #666;">Опис</label>
                            <input type="text" name="spec_description" class="form-control" placeholder="Короткий опис спеціалізації">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-size: 12px; color: #666; margin-right: 100px;">Іконка</label>
                            <input type="text" name="spec_icon" class="form-control icon-input" placeholder="📖" maxlength="2" value="📖">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        ✅ Додати спеціалізацію
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal for resolve/reject -->
    <div id="actionModal" class="modal">
        <div class="modal-content">
            <h3 id="modalTitle"></h3>
            <form method="post" id="actionForm">
                <input type="hidden" name="complaint_id" id="modalComplaintId">
                <input type="hidden" name="action" id="modalAction">
                
                <div class="form-group">
                    <label for="moderator_note">Примітка:</label>
                    <textarea name="moderator_note" id="moderator_note" required 
                              placeholder="Опишіть прийняте рішення та причини..."></textarea>
                </div>
                
                <div class="form-group" id="blockUserSection" style="display: none;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="block_user" id="block_user" value="1" style="width: 20px; height: 20px;">
                        <span style="color: #dc2626; font-weight: bold;">🚫 Заблокувати користувача (порушника)</span>
                    </label>
                    <small style="color: #666;">Користувач втратить доступ до всіх функцій платформи</small>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Підтвердити</button>
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">Скасувати</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(complaintId, action) {
            const modal = document.getElementById('actionModal');
            const title = document.getElementById('modalTitle');
            const modalComplaintId = document.getElementById('modalComplaintId');
            const modalAction = document.getElementById('modalAction');
            const blockUserSection = document.getElementById('blockUserSection');
            
            modalComplaintId.value = complaintId;
            modalAction.value = action;
            
            if (action === 'resolve') {
                title.textContent = '✅ Вирішити скаргу';
                blockUserSection.style.display = 'block';
            } else {
                title.textContent = '❌ Відхилити скаргу';
                blockUserSection.style.display = 'none';
            }
            
            modal.classList.add('active');
        }
        
        function closeModal() {
            const modal = document.getElementById('actionModal');
            modal.classList.remove('active');
            document.getElementById('moderator_note').value = '';
            document.getElementById('block_user').checked = false;
        }
        
        // Close modal on background click
        document.getElementById('actionModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>