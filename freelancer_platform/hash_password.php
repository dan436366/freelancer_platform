<?php
$hashed = '';
$name = '';
$email = '';
$password = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST["name"];
    $email = $_POST["email"];
    $password = $_POST["password"];
    $hashed = password_hash($password, PASSWORD_DEFAULT);
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Генератор SQL для модератора</title>
    <link rel="stylesheet" href="css/login_style.css">
    <style>
        .hash-result {
            margin-top: 20px;
            padding: 15px;
            background: #f0f9ff;
            border: 2px solid #3b82f6;
            border-radius: 8px;
        }
        .hash-result h3 {
            margin-top: 0;
            color: #1e40af;
        }
        .info-item {
            background: #fff;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            border: 1px solid #ddd;
        }
        .info-item strong {
            color: #1e40af;
        }
        .hash-value {
            background: #fff;
            padding: 10px;
            border-radius: 4px;
            word-break: break-all;
            font-family: monospace;
            font-size: 14px;
            border: 1px solid #ddd;
            margin: 10px 0;
        }
        .copy-btn {
            background: #10b981;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .copy-btn:hover {
            background: #059669;
        }
        .sql-example {
            background: #fef3c7;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            border: 2px solid #f59e0b;
        }
        .sql-example h4 {
            margin-top: 0;
            color: #92400e;
        }
        .sql-code {
            background: #fff;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 13px;
            overflow-x: auto;
            border: 1px solid #ddd;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card">
            <div class="logo">👮</div>
            <h1>Створення модератора</h1>
            <p class="subtitle">Згенеруйте SQL-запит для додавання модератора</p>

            <form method="post">
                <div class="form-group">
                    <label for="name">👤 Ім'я модератора</label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           placeholder="Введіть повне ім'я" 
                           required
                           value="<?= htmlspecialchars($name) ?>">
                </div>

                <div class="form-group">
                    <label for="email">📧 Email адреса</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           placeholder="moderator@example.com" 
                           required
                           value="<?= htmlspecialchars($email) ?>">
                </div>

                <div class="form-group">
                    <label for="password">🔒 Пароль</label>
                    <input type="text" 
                           id="password" 
                           name="password" 
                           placeholder="Введіть пароль" 
                           required
                           value="<?= htmlspecialchars($password) ?>">
                    <small style="color: #666; display: block; margin-top: 5px;">
                        💡 Використовуйте складний пароль (мін. 8 символів)
                    </small>
                </div>

                <button type="submit" class="btn btn-primary">
                    ⚡ Згенерувати SQL-запит
                </button>
            </form>

            <?php if (!empty($hashed)): ?>
                <div class="hash-result">
                    <h3>✅ Дані модератора згенеровано!</h3>
                    
                    <div class="info-item">
                        <strong>👤 Ім'я:</strong> <?= htmlspecialchars($name) ?>
                    </div>
                    
                    <div class="info-item">
                        <strong>📧 Email:</strong> <?= htmlspecialchars($email) ?>
                    </div>
                    
                    <div class="info-item">
                        <strong>🔒 Пароль:</strong> <?= htmlspecialchars($password) ?>
                    </div>
                    
                    <div class="info-item">
                        <strong>🔐 Хешований пароль:</strong>
                        <div class="hash-value" style="margin-top: 5px;"><?= htmlspecialchars($hashed) ?></div>
                    </div>
                </div>

                <div class="sql-example">
                    <h4>📝 SQL-запит для виконання:</h4>
                    <div class="sql-code" id="sqlQuery">INSERT INTO users (name, email, password, role) 
VALUES ('<?= addslashes($name) ?>', '<?= addslashes($email) ?>', '<?= addslashes($hashed) ?>', 'moderator');</div>
                    <button class="copy-btn" onclick="copySQL()" style="margin-top: 10px;">📋 Копіювати SQL-запит</button>
                    
                    <div style="margin-top: 15px; padding: 10px; background: #fff; border-radius: 4px; font-size: 13px;">
                        <strong>📌 Інструкція:</strong><br>
                        1. Скопіюйте SQL-запит вище<br>
                        2. Відкрийте phpMyAdmin або інший SQL-клієнт<br>
                        3. Виберіть вашу базу даних<br>
                        4. Вставте і виконайте запит<br>
                        5. Модератор зможе увійти через moderator_login.php
                    </div>
                </div>
            <?php endif; ?>

            <div class="back-link" style="margin-top: 20px;">
                <a href="index.php">← Повернутися на головну</a>
            </div>
        </div>
    </div>

    <script>
        function copySQL() {
            const sqlQuery = document.getElementById('sqlQuery').textContent;
            navigator.clipboard.writeText(sqlQuery).then(() => {
                alert('✅ SQL-запит скопійовано в буфер обміну!');
            });
        }
    </script>
</body>
</html>