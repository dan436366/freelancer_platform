<?php
require 'db.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];
    $password = $_POST["password"];

    $stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email = ? AND role = 'moderator'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows == 1) {
        $stmt->bind_result($id, $name, $hashed_password, $role);
        $stmt->fetch();
        if (password_verify($password, $hashed_password)) {
            $_SESSION["user_id"] = $id;
            $_SESSION["user_name"] = $name;
            $_SESSION["role"] = $role;
            header("Location: moderator_dashboard.php");
            exit();
        }
    }
    $error = "Неправильний email або пароль, або у вас немає прав модератора";
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вхід для модераторів</title>
    <link rel="stylesheet" href="css/login_style.css">
</head>
<body>
    <div class="container">
        <div class="login-card">
            <div class="logo">👮</div>
            <h1>Панель модератора</h1>
            <p class="subtitle">Вхід для модераторів системи</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    ⚠️ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <label for="email">📧 Email адреса модератора</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           placeholder="Введіть ваш робочий email" 
                           required
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                </div>

                <div class="form-group">
                    <label for="password">🔒 Пароль</label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           placeholder="Введіть ваш пароль" 
                           required>
                </div>

                <button type="submit" class="btn btn-primary">
                    🚀 Увійти як модератор
                </button>
            </form>

            <div class="divider">
                <span>або</span>
            </div>

            <div class="auth-links">
                <p>Звичайний користувач? <a href="login.php">Увійти як користувач</a></p>
            </div>

            <div class="back-link">
                <a href="index.php">← Повернутися на головну</a>
            </div>
        </div>
    </div>

    <script src="js/login.js"></script>
</body>
</html>