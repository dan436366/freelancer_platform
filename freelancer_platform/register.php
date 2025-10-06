<?php
require 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST["name"];
    $email = $_POST["email"];
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT);
    $role = $_POST["role"];

    // Перевіряємо, чи email вже існує
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $error = "Користувач з таким email вже існує";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $password, $role);
        
        if ($stmt->execute()) {
            $success = "Реєстрація успішна! Тепер ви можете увійти.";
            $form_data = [];
        } else {
            $error = "Помилка при реєстрації. Спробуйте ще раз.";
        }
        $stmt->close();
    }
    $check_stmt->close();
    
    // дані форми для повторного відображення у разі помилки
    if (!isset($success)) {
        $form_data = $_POST;
    }
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Реєстрація</title>
    <link rel="stylesheet" href="css/register_style.css">
</head>
<body>
    <div class="container">
        <div class="register-card">
            <div class="logo">📝</div>
            <h1>Реєстрація</h1>
            <p class="subtitle">Створіть новий акаунт</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    ⚠️ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    ✅ <?= htmlspecialchars($success) ?>
                    <br><br>
                    <a href="login.php" style="color: #27ae60; font-weight: bold;">Перейти до входу →</a>
                </div>
            <?php else: ?>

            <form method="post" id="registrationForm">
                <div class="form-group">
                    <label for="name">👤 Повне ім'я</label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           placeholder="Введіть ваше повне ім'я" 
                           required
                           value="<?= isset($form_data['name']) ? htmlspecialchars($form_data['name']) : '' ?>">
                </div>

                <div class="form-group">
                    <label for="email">📧 Email адреса</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           placeholder="Введіть ваш email" 
                           required
                           value="<?= isset($form_data['email']) ? htmlspecialchars($form_data['email']) : '' ?>">
                </div>

                <div class="form-group">
                    <label for="password">🔒 Пароль</label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           placeholder="Створіть надійний пароль" 
                           required
                           minlength="6">
                    <div class="password-strength" id="passwordStrength"></div>
                </div>

                <div class="form-group">
                    <label>👥 Оберіть роль</label>
                    <div class="role-selection">
                        <div class="role-option">
                            <input type="radio" 
                                   id="student" 
                                   name="role" 
                                   value="student" 
                                   <?= (!isset($form_data['role']) || $form_data['role'] === 'student') ? 'checked' : '' ?>>
                            <label for="student">
                                <span class="role-icon">🏢</span>
                                <span>Клієнт</span>
                            </label>
                        </div>
                        <div class="role-option">
                            <input type="radio" 
                                   id="tutor" 
                                   name="role" 
                                   value="tutor"
                                   <?= (isset($form_data['role']) && $form_data['role'] === 'tutor') ? 'checked' : '' ?>>
                            <label for="tutor">
                                <span class="role-icon">💻</span>
                                <span>Фрілансер</span>
                            </label>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    🚀 Зареєструватися
                </button>
            </form>

            <?php endif; ?>

            <div class="divider">
                <span>або</span>
            </div>

            <div class="auth-links">
                <p>Вже маєте акаунт? <a href="login.php">Увійти</a></p>
            </div>

            <div class="back-link">
                <a href="index.php">← Повернутися на головну</a>
            </div>
        </div>
    </div>

    <script src="js/register.js"></script>
</body>
</html>