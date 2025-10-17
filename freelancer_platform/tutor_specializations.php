<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tutor') {
    header('Location: login.php');
    exit();
}

$tutor_id = $_SESSION['user_id'];

// Обробка додавання/оновлення спеціалізацій
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_specialization':
                $specialization_id = $_POST['specialization_id'];
                $experience_years = (int)$_POST['experience_years'];
                $price_per_hour = (float)$_POST['price_per_hour'];
                $description = $_POST['description'];
                
                $stmt = $conn->prepare("INSERT INTO tutor_specializations (tutor_id, specialization_id, experience_years, price_per_hour, description) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE experience_years = ?, price_per_hour = ?, description = ?");
                $stmt->bind_param("iidsiids", $tutor_id, $specialization_id, $experience_years, $price_per_hour, $description, $experience_years, $price_per_hour, $description);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'Спеціалізацію успішно додано!';
                } else {
                    $_SESSION['error_message'] = 'Помилка при додаванні спеціалізації.';
                }
                break;
                
            case 'remove_specialization':
                $specialization_id = $_POST['specialization_id'];
                $stmt = $conn->prepare("DELETE FROM tutor_specializations WHERE tutor_id = ? AND specialization_id = ?");
                $stmt->bind_param("ii", $tutor_id, $specialization_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'Спеціалізація видалена!';
                } else {
                    $_SESSION['error_message'] = 'Помилка при видаленні спеціалізації.';
                }
                break;
                
            case 'update_profile':
                $name = trim($_POST['name']);
                $bio = $_POST['bio'];
                $phone = $_POST['phone'];
                
                // Перевірка чи ім'я не пусте
                if (empty($name)) {
                    $_SESSION['error_message'] = 'Ім\'я не може бути порожнім!';
                } else {
                    $stmt = $conn->prepare("UPDATE users SET name = ?, bio = ?, phone = ? WHERE id = ?");
                    $stmt->bind_param("sssi", $name, $bio, $phone, $tutor_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['user_name'] = $name; // Оновлюємо ім'я в сесії
                        $_SESSION['success_message'] = 'Профіль оновлено!';
                    } else {
                        $_SESSION['error_message'] = 'Помилка при оновленні профілю.';
                    }
                }
                break;
        }
    }
    
    header('Location: tutor_specializations.php');
    exit();
}

// Отримуємо всі доступні спеціалізації
$all_specializations = $conn->query("SELECT * FROM specializations ORDER BY name");

// Отримуємо спеціалізації викладача
$tutor_specializations_stmt = $conn->prepare("
    SELECT ts.*, s.name, s.icon 
    FROM tutor_specializations ts 
    JOIN specializations s ON ts.specialization_id = s.id 
    WHERE ts.tutor_id = ? 
    ORDER BY s.name
");
$tutor_specializations_stmt->bind_param("i", $tutor_id);
$tutor_specializations_stmt->execute();
$tutor_specializations = $tutor_specializations_stmt->get_result();

// Отримуємо інформацію про викладача
$tutor_info_stmt = $conn->prepare("SELECT name, email, bio, phone FROM users WHERE id = ?");
$tutor_info_stmt->bind_param("i", $tutor_id);
$tutor_info_stmt->execute();
$tutor_info = $tutor_info_stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мої спеціалізації</title>
    <link rel="stylesheet" href="css/tutor_specializations_style.css">
    <style>
        .contact-moderator-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 20px;
            color: white;
            text-align: center;
            margin-top: 20px;
        }
        
        .contact-moderator-card h4 {
            margin: 0 0 10px 0;
            font-size: 18px;
        }
        
        .contact-moderator-card p {
            margin: 0 0 15px 0;
            opacity: 0.95;
            font-size: 14px;
        }
        
        .btn-contact-moderator {
            background: white;
            color: #667eea;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .btn-contact-moderator:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="welcome-section">
                <div class="user-avatar">🎯</div>
                <div class="welcome-text">
                    <h1>Мої спеціалізації</h1>
                    <span class="role-badge">
                        Управління профілем
                    </span>
                </div>
            </div>
            <div class="header-actions">
                <a href="tutor_dashboard.php" class="btn btn-primary">
                    ← Повернутися
                </a>
                <a href="logout.php" class="btn btn-outline">
                    🚪 Вийти
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                ✅ <?= htmlspecialchars($_SESSION['success_message']) ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                ❌ <?= htmlspecialchars($_SESSION['error_message']) ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Профіль викладача -->
        <div class="profile-section">
            <div class="profile-info">
                <div class="profile-name">👨‍💻 <?= htmlspecialchars($tutor_info['name']) ?></div>
                <div class="profile-email">📧 <?= htmlspecialchars($tutor_info['email']) ?></div>
                <?php if ($tutor_info['phone']): ?>
                    <div class="profile-email">📞 <?= htmlspecialchars($tutor_info['phone']) ?></div>
                <?php endif; ?>
                <?php if ($tutor_info['bio']): ?>
                    <div class="profile-bio">
                        <strong>Про мене:</strong><br>
                        <?= nl2br(htmlspecialchars($tutor_info['bio'])) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="main-grid">
            <!-- Поточні спеціалізації -->
            <div class="section-card">
                <div class="section-header">
                    <h3 class="section-title">📚 Мої спеціалізації</h3>
                </div>
                <div class="section-content">
                    <?php if ($tutor_specializations->num_rows > 0): ?>
                        <?php while ($spec = $tutor_specializations->fetch_assoc()): ?>
                            <div class="specialization-card">
                                <div class="spec-header">
                                    <div class="spec-info">
                                        <h4><?= $spec['icon'] ?> <?= htmlspecialchars($spec['name']) ?></h4>
                                    </div>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="remove_specialization">
                                        <input type="hidden" name="specialization_id" value="<?= $spec['specialization_id'] ?>">
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Видалити спеціалізацію?')">
                                            🗑️ Видалити
                                        </button>
                                    </form>
                                </div>
                                
                                <div class="spec-details">
                                    <div class="spec-detail">
                                        <div class="spec-detail-value"><?= $spec['experience_years'] ?></div>
                                        <div class="spec-detail-label">років досвіду</div>
                                    </div>
                                    <div class="spec-detail">
                                        <div class="spec-detail-value"><?= number_format($spec['price_per_hour'], 0) ?> ₴</div>
                                        <div class="spec-detail-label">за годину</div>
                                    </div>
                                </div>
                                
                                <?php if ($spec['description']): ?>
                                    <div class="spec-description">
                                        "<?= htmlspecialchars($spec['description']) ?>"
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">📚</div>
                            <h4>Спеціалізацій не додано</h4>
                            <p>Додайте спеціалізації, щоб клієнти могли знайти вас.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Форма додавання спеціалізації -->
            <div class="section-card">
                <div class="section-header">
                    <h3 class="section-title">➕ Додати спеціалізацію</h3>
                </div>
                <div class="section-content">
                    <form method="post">
                        <input type="hidden" name="action" value="add_specialization">
                        
                        <div class="form-group">
                            <label class="form-label">Спеціалізація</label>
                            <select name="specialization_id" class="form-control" required>
                                <option value="">Оберіть спеціалізацію</option>
                                <?php while ($spec = $all_specializations->fetch_assoc()): ?>
                                    <option value="<?= $spec['id'] ?>"><?= $spec['icon'] ?> <?= htmlspecialchars($spec['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Досвід (років)</label>
                            <input type="number" name="experience_years" class="form-control" min="0" max="50" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Орієнтовна ціна (₴)</label>
                            <input type="number" name="price_per_hour" class="form-control" min="0" step="0.01" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Опис (необов'язково)</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Розкажіть коротко про себе та як ви працюєте в цій сфері"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            ✅ Додати спеціалізацію
                        </button>
                    </form>
                    
                    <!-- Блок звʼязку з модератором -->
                    <div class="contact-moderator-card">
                        <h4>🔍 Не знайшли свою спеціалізацію?</h4>
                        <p>Зв'яжіться з модератором, і він додасть її до списку!</p>
                        <a href="contact_moderator.php" class="btn-contact-moderator">
                            💬 Написати модератору
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Форма оновлення профілю -->
        <div class="section-card">
            <div class="section-header">
                <h3 class="section-title">👤 Оновити профіль</h3>
            </div>
            <div class="section-content">
                <form method="post">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-group">
                        <label class="form-label">Ім'я</label>
                        <input type="text" name="name" class="form-control" placeholder="Ваше повне ім'я" value="<?= htmlspecialchars($tutor_info['name']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Про себе</label>
                        <textarea name="bio" class="form-control" rows="4" placeholder="Розкажіть про свою освіту, досвід роботи, методики викладання..."><?= htmlspecialchars($tutor_info['bio'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Телефон</label>
                        <input type="tel" name="phone" class="form-control" placeholder="+380..." value="<?= htmlspecialchars($tutor_info['phone'] ?? '') ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-success">
                        💾 Зберегти зміни
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="js/tutor_specializations.js"></script>
</body>
</html>