<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'db.php';

echo "<h1>🔧 Виправлення ролей користувачів</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    table { border-collapse: collapse; width: 100%; margin-top: 10px; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #4CAF50; color: white; }
    .btn { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
    .btn:hover { background: #45a049; }
</style>";

// Перевіряємо поточний стан
echo "<div class='section'>";
echo "<h2>1️⃣ Поточний стан бази даних</h2>";

$result = $conn->query("SELECT id, name, email, role FROM users");
echo "<table>";
echo "<tr><th>ID</th><th>Ім'я</th><th>Email</th><th>Роль (поточна)</th><th>Є заявки як викладач?</th></tr>";

$users_data = [];
while ($row = $result->fetch_assoc()) {
    // Перевіряємо, чи є заявки як викладач
    $check_tutor = $conn->query("SELECT COUNT(*) as cnt FROM lesson_requests WHERE tutor_id = " . $row['id']);
    $is_tutor = $check_tutor->fetch_assoc()['cnt'] > 0;
    
    // Перевіряємо, чи є заявки як студент
    $check_student = $conn->query("SELECT COUNT(*) as cnt FROM lesson_requests WHERE student_id = " . $row['id']);
    $is_student = $check_student->fetch_assoc()['cnt'] > 0;
    
    // Перевіряємо спеціалізації
    $check_spec = $conn->query("SELECT COUNT(*) as cnt FROM tutor_specializations WHERE tutor_id = " . $row['id']);
    $has_specializations = $check_spec->fetch_assoc()['cnt'] > 0;
    
    $users_data[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'email' => $row['email'],
        'current_role' => $row['role'],
        'is_tutor' => $is_tutor || $has_specializations,
        'is_student' => $is_student
    ];
    
    $role_display = empty($row['role']) ? "<span class='error'>ПУСТО!</span>" : $row['role'];
    $tutor_mark = ($is_tutor || $has_specializations) ? "✅ Так" : "❌ Ні";
    
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['email']) . "</td>";
    echo "<td>" . $role_display . "</td>";
    echo "<td>" . $tutor_mark . "</td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// Форма для виправлення
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<div class='section'>";
    echo "<h2>2️⃣ Автоматичне виправлення</h2>";
    echo "<p>Натисніть кнопку нижче, щоб автоматично призначити ролі користувачам:</p>";
    echo "<ul>";
    echo "<li>Користувачі з спеціалізаціями або заявками як викладач → <strong>tutor</strong></li>";
    echo "<li>Користувачі з заявками як студент → <strong>student</strong></li>";
    echo "<li>Інші користувачі залишаться без змін</li>";
    echo "</ul>";
    echo "<form method='post'>";
    echo "<button type='submit' name='fix_roles' class='btn'>🔧 Виправити ролі автоматично</button>";
    echo "</form>";
    echo "</div>";
} else if (isset($_POST['fix_roles'])) {
    echo "<div class='section'>";
    echo "<h2>2️⃣ Результати виправлення</h2>";
    
    $fixed_count = 0;
    
    foreach ($users_data as $user) {
        $new_role = null;
        
        // Визначаємо роль на основі активності
        if ($user['is_tutor']) {
            $new_role = 'tutor';
        } else if ($user['is_student']) {
            $new_role = 'student';
        }
        
        // Якщо роль визначена і вона відрізняється від поточної
        if ($new_role && $new_role !== $user['current_role']) {
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->bind_param("si", $new_role, $user['id']);
            
            if ($stmt->execute()) {
                echo "<p class='success'>✅ Користувач <strong>" . htmlspecialchars($user['name']) . "</strong> (ID: " . $user['id'] . ") → роль змінено на <strong>$new_role</strong></p>";
                $fixed_count++;
            } else {
                echo "<p class='error'>❌ Помилка оновлення користувача " . htmlspecialchars($user['name']) . "</p>";
            }
        }
    }
    
    if ($fixed_count === 0) {
        echo "<p class='warning'>⚠️ Жодної ролі не було змінено. Можливо, всі ролі вже встановлені правильно.</p>";
    } else {
        echo "<p class='success'><strong>🎉 Виправлено $fixed_count користувачів!</strong></p>";
    }
    
    echo "<p><a href='debug_db.php' class='btn' style='display:inline-block; text-decoration:none;'>🔍 Перевірити результат</a></p>";
    echo "<p><a href='dashboard.php' class='btn' style='display:inline-block; text-decoration:none; background:#2196F3;'>🏠 Перейти на головну</a></p>";
    echo "</div>";
}

// Додатковий розділ - ручне призначення
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['fix_roles'])) {
    echo "<div class='section'>";
    echo "<h2>3️⃣ Ручне призначення ролей (опціонально)</h2>";
    echo "<p>Якщо автоматичне призначення не спрацювало, ви можете вручну встановити ролі:</p>";
    echo "<form method='post'>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Ім'я</th><th>Email</th><th>Нова роль</th></tr>";
    
    foreach ($users_data as $user) {
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . htmlspecialchars($user['name']) . "</td>";
        echo "<td>" . htmlspecialchars($user['email']) . "</td>";
        echo "<td>";
        echo "<select name='role_" . $user['id'] . "'>";
        echo "<option value=''>-- Не змінювати --</option>";
        echo "<option value='student'>Student (Клієнт)</option>";
        echo "<option value='tutor'>Tutor (Фрілансер)</option>";
        echo "</select>";
        echo "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    echo "<button type='submit' name='manual_fix' class='btn' style='margin-top:10px;'>💾 Зберегти зміни</button>";
    echo "</form>";
    echo "</div>";
} else if (isset($_POST['manual_fix'])) {
    echo "<div class='section'>";
    echo "<h2>3️⃣ Результати ручного призначення</h2>";
    
    $manual_count = 0;
    
    foreach ($users_data as $user) {
        $field_name = 'role_' . $user['id'];
        if (isset($_POST[$field_name]) && !empty($_POST[$field_name])) {
            $new_role = $_POST[$field_name];
            
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->bind_param("si", $new_role, $user['id']);
            
            if ($stmt->execute()) {
                echo "<p class='success'>✅ Користувач <strong>" . htmlspecialchars($user['name']) . "</strong> → роль: <strong>$new_role</strong></p>";
                $manual_count++;
            }
        }
    }
    
    echo "<p class='success'><strong>🎉 Оновлено $manual_count користувачів!</strong></p>";
    echo "<p><a href='debug_db.php' class='btn' style='display:inline-block; text-decoration:none;'>🔍 Перевірити результат</a></p>";
    echo "</div>";
}

$conn->close();
?>