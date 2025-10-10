<?php
// Увімкнути відображення всіх помилок
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Діагностика бази даних</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    table { border-collapse: collapse; width: 100%; margin-top: 10px; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #4CAF50; color: white; }
    code { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; }
</style>";

// Підключення до бази даних
require 'db.php';

// 1. Перевірка підключення
echo "<div class='section'>";
echo "<h2>1️⃣ Перевірка підключення до MySQL</h2>";
if ($conn->connect_error) {
    echo "<p class='error'>❌ Помилка підключення: " . $conn->connect_error . "</p>";
    exit();
} else {
    echo "<p class='success'>✅ Підключення успішне!</p>";
    echo "<p>Версія MySQL: <code>" . $conn->server_info . "</code></p>";
    echo "<p>Кодування з'єднання: <code>" . $conn->character_set_name() . "</code></p>";
}
echo "</div>";

// 2. Перевірка бази даних
echo "<div class='section'>";
echo "<h2>2️⃣ Перевірка бази даних</h2>";
$db_name = 'freelancer_platform';
$result = $conn->query("SELECT DATABASE() as db");
if ($result) {
    $row = $result->fetch_assoc();
    if ($row['db'] === $db_name) {
        echo "<p class='success'>✅ База даних <code>$db_name</code> активна</p>";
    } else {
        echo "<p class='error'>❌ Активна інша база даних: <code>" . $row['db'] . "</code></p>";
    }
} else {
    echo "<p class='error'>❌ Помилка перевірки бази даних</p>";
}
echo "</div>";

// 3. Перевірка таблиць
echo "<div class='section'>";
echo "<h2>3️⃣ Перевірка таблиць</h2>";
$required_tables = ['users', 'lesson_requests', 'messages', 'ratings', 'specializations', 'tutor_specializations'];
$result = $conn->query("SHOW TABLES");
$existing_tables = [];
if ($result) {
    while ($row = $result->fetch_array()) {
        $existing_tables[] = $row[0];
    }
    
    echo "<table>";
    echo "<tr><th>Таблиця</th><th>Статус</th><th>Кількість записів</th></tr>";
    
    foreach ($required_tables as $table) {
        if (in_array($table, $existing_tables)) {
            $count_result = $conn->query("SELECT COUNT(*) as cnt FROM `$table`");
            $count = $count_result ? $count_result->fetch_assoc()['cnt'] : 0;
            echo "<tr><td><code>$table</code></td><td class='success'>✅ Існує</td><td>$count</td></tr>";
        } else {
            echo "<tr><td><code>$table</code></td><td class='error'>❌ Відсутня</td><td>-</td></tr>";
        }
    }
    echo "</table>";
} else {
    echo "<p class='error'>❌ Помилка отримання списку таблиць</p>";
}
echo "</div>";

// 4. Перевірка структури таблиці users
echo "<div class='section'>";
echo "<h2>4️⃣ Структура таблиці <code>users</code></h2>";
$result = $conn->query("DESCRIBE users");
if ($result) {
    echo "<table>";
    echo "<tr><th>Поле</th><th>Тип</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td><code>" . $row['Field'] . "</code></td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>❌ Помилка отримання структури таблиці</p>";
}
echo "</div>";

// 5. Перевірка даних користувачів
echo "<div class='section'>";
echo "<h2>5️⃣ Дані користувачів</h2>";
$result = $conn->query("SELECT id, name, email, role, created_at FROM users LIMIT 10");
if ($result && $result->num_rows > 0) {
    echo "<p class='success'>✅ Знайдено " . $result->num_rows . " користувачів</p>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Ім'я</th><th>Email</th><th>Роль</th><th>Дата створення</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td><code>" . ($row['role'] ?? 'NULL') . "</code></td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='warning'>⚠️ Користувачів не знайдено</p>";
}
echo "</div>";

// 6. Тестування проблемного запиту для фрілансера
echo "<div class='section'>";
echo "<h2>6️⃣ Тестування запиту статистики фрілансера</h2>";

// Знайдемо першого фрілансера
$tutor_result = $conn->query("SELECT id FROM users WHERE role = 'tutor' LIMIT 1");
if ($tutor_result && $tutor_result->num_rows > 0) {
    $tutor = $tutor_result->fetch_assoc();
    $tutor_id = $tutor['id'];
    
    echo "<p>Тестуємо для фрілансера ID: <code>$tutor_id</code></p>";
    
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT lr.id) as total_requests,
            SUM(CASE WHEN lr.status = 'Очікує' THEN 1 ELSE 0 END) as pending_requests,
            SUM(CASE WHEN lr.status = 'Прийнята' THEN 1 ELSE 0 END) as accepted_requests,
            COALESCE(AVG(r.rating), 0) as avg_rating,
            COUNT(DISTINCT r.id) as total_ratings
        FROM lesson_requests lr
        LEFT JOIN ratings r ON lr.tutor_id = r.tutor_id
        WHERE lr.tutor_id = ?
        GROUP BY lr.tutor_id
    ");
    
    $stmt->bind_param("i", $tutor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "<p><strong>Результат запиту:</strong></p>";
    
    if ($result->num_rows > 0) {
        $stats = $result->fetch_assoc();
        echo "<table>";
        echo "<tr><th>Параметр</th><th>Значення</th><th>Тип</th></tr>";
        foreach ($stats as $key => $value) {
            $type = gettype($value);
            $display_value = $value === null ? 'NULL' : $value;
            echo "<tr><td><code>$key</code></td><td>$display_value</td><td>$type</td></tr>";
        }
        echo "</table>";
        
        // Перевірка на NULL
        echo "<h3>Перевірка NULL значень:</h3>";
        foreach ($stats as $key => $value) {
            if ($value === null) {
                echo "<p class='error'>❌ Поле <code>$key</code> має значення NULL</p>";
            } else {
                echo "<p class='success'>✅ Поле <code>$key</code> = $value</p>";
            }
        }
    } else {
        echo "<p class='warning'>⚠️ Запит не повернув результатів</p>";
        echo "<p>Це означає, що у фрілансера немає заявок. Перевіряємо вручну:</p>";
        
        $check = $conn->query("SELECT COUNT(*) as cnt FROM lesson_requests WHERE tutor_id = $tutor_id");
        $cnt = $check->fetch_assoc()['cnt'];
        echo "<p>Кількість заявок для tutor_id=$tutor_id: <code>$cnt</code></p>";
    }
} else {
    echo "<p class='warning'>⚠️ Фрілансерів не знайдено в базі даних</p>";
}
echo "</div>";

// 7. Перевірка кодування
echo "<div class='section'>";
echo "<h2>7️⃣ Перевірка кодування</h2>";
$result = $conn->query("SHOW VARIABLES LIKE 'character_set%'");
echo "<table>";
echo "<tr><th>Параметр</th><th>Значення</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr><td><code>" . $row['Variable_name'] . "</code></td><td>" . $row['Value'] . "</td></tr>";
}
echo "</table>";
echo "</div>";

// 8. Перевірка помилок PHP
echo "<div class='section'>";
echo "<h2>8️⃣ Налаштування PHP</h2>";
echo "<table>";
echo "<tr><th>Параметр</th><th>Значення</th></tr>";
echo "<tr><td>display_errors</td><td>" . ini_get('display_errors') . "</td></tr>";
echo "<tr><td>error_reporting</td><td>" . error_reporting() . "</td></tr>";
echo "<tr><td>mysqli extension</td><td>" . (extension_loaded('mysqli') ? '✅ Завантажено' : '❌ Не завантажено') . "</td></tr>";
echo "</table>";
echo "</div>";

echo "<div class='section'>";
echo "<h2>✅ Діагностика завершена</h2>";
echo "<p>Якщо ви бачите помилки вище, надішліть мені скріншот цієї сторінки.</p>";
echo "</div>";

$conn->close();
?>