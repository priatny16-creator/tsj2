<?php
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';

echo "<h1>Установка ТСЖ \"Наш Дом\"</h1>";
echo "<pre>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = trim($_POST['db_host'] ?? 'localhost');
    $db_name = trim($_POST['db_name'] ?? 'tszh_db');
    $db_user = trim($_POST['db_user'] ?? 'root');
    $db_pass = trim($_POST['db_pass'] ?? '');

    try {
        $pdo = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$db_name`");
        echo "База данных создана.\n";

        $sql = file_get_contents(__DIR__ . '/database.sql');
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $stmt) {
            if (!empty($stmt) && stripos($stmt, 'CREATE DATABASE') === false && stripos($stmt, 'USE ') === false) {
                $pdo->exec($stmt);
            }
        }
        echo "Таблицы созданы.\n";

        $hash = password_hash('admin123', PASSWORD_DEFAULT);

        $pdo->exec("DELETE FROM bill_items");
        $pdo->exec("DELETE FROM bills");
        $pdo->exec("DELETE FROM meter_readings");
        $pdo->exec("DELETE FROM residents");
        $pdo->exec("DELETE FROM apartments");
        $pdo->exec("DELETE FROM tariffs");
        $pdo->exec("DELETE FROM users");

        $stmt = $pdo->prepare("INSERT INTO users (login, password, full_name, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin', $hash, 'Администратор', 'admin']);
        $stmt->execute(['ivanov', $hash, 'Иванов Иван Иванович', 'resident']);
        $stmt->execute(['petrova', $hash, 'Петрова Мария Сергеевна', 'resident']);
        echo "Пользователи созданы.\n";

        $pdo2 = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $users = $pdo2->query("SELECT id, login FROM users")->fetchAll(PDO::FETCH_ASSOC);
        $user_map = [];
        foreach ($users as $u) $user_map[$u['login']] = $u['id'];

        $stmt = $pdo2->prepare("INSERT INTO apartments (number, floor, area, rooms, owner_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['1', 1, 45.50, 2, $user_map['ivanov']]);
        $stmt->execute(['2', 1, 62.30, 3, $user_map['petrova']]);
        $stmt->execute(['3', 2, 38.00, 1, null]);
        $stmt->execute(['4', 2, 55.80, 2, null]);
        echo "Квартиры созданы.\n";

        $apts = $pdo2->query("SELECT id, number FROM apartments")->fetchAll(PDO::FETCH_ASSOC);
        $apt_map = [];
        foreach ($apts as $a) $apt_map[$a['number']] = $a['id'];

        $stmt = $pdo2->prepare("INSERT INTO residents (user_id, apartment_id, phone, registered_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_map['ivanov'], $apt_map['1'], '+7 (999) 123-45-67', '2020-01-15']);
        $stmt->execute([$user_map['petrova'], $apt_map['2'], '+7 (999) 765-43-21', '2019-06-10']);
        echo "Жильцы привязаны.\n";

        $stmt = $pdo2->prepare("INSERT INTO tariffs (name, unit, price_per_unit) VALUES (?, ?, ?)");
        $stmt->execute(['Холодная вода', 'куб.м', 40.4800]);
        $stmt->execute(['Горячая вода', 'куб.м', 205.1500]);
        $stmt->execute(['Электроэнергия', 'кВт*ч', 6.7300]);
        $stmt->execute(['Газ', 'куб.м', 7.1200]);
        $stmt->execute(['Отопление', 'Гкал', 2546.8500]);
        echo "Тарифы созданы.\n";

        $config_content = "<?php\nsession_start();\n\n";
        $config_content .= "\$db_host = '$db_host';\n";
        $config_content .= "\$db_name = '$db_name';\n";
        $config_content .= "\$db_user = '$db_user';\n";
        $config_content .= "\$db_pass = '$db_pass';\n\n";
        $config_content .= "try {\n";
        $config_content .= "    \$pdo = new PDO(\"mysql:host=\$db_host;dbname=\$db_name;charset=utf8mb4\", \$db_user, \$db_pass);\n";
        $config_content .= "    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n";
        $config_content .= "    \$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);\n";
        $config_content .= "} catch (PDOException \$e) {\n";
        $config_content .= "    die(\"Ошибка подключения к БД: \" . \$e->getMessage());\n";
        $config_content .= "}\n\n";
        $config_content .= "function isLoggedIn() {\n    return isset(\$_SESSION['user_id']);\n}\n\n";
        $config_content .= "function isAdmin() {\n    return isset(\$_SESSION['role']) && \$_SESSION['role'] === 'admin';\n}\n\n";
        $config_content .= "function redirect(\$url) {\n    header(\"Location: \$url\");\n    exit;\n}\n\n";
        $config_content .= "function escape(\$str) {\n    return htmlspecialchars(\$str, ENT_QUOTES, 'UTF-8');\n}\n";

        file_put_contents(__DIR__ . '/config.php', $config_content);
        echo "Файл config.php обновлён.\n";

        echo "\n============================\n";
        echo "УСТАНОВКА ЗАВЕРШЕНА!\n";
        echo "============================\n\n";
        echo "Логин: admin / admin123 (администратор)\n";
        echo "Логин: ivanov / admin123 (жилец)\n";
        echo "Логин: petrova / admin123 (жилец)\n\n";
        echo "ВАЖНО: Удалите файл install.php после установки!\n";
        echo "</pre>";
        echo "<p><a href='index.php' style='font-size:18px;'>Перейти на сайт &rarr;</a></p>";
        exit;
    } catch (PDOException $e) {
        echo "ОШИБКА: " . $e->getMessage() . "\n";
        echo "</pre>";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Установка - ТСЖ "Наш Дом"</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .box { background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); max-width: 500px; width: 100%; }
        h1 { color: #1e3a5f; margin-bottom: 20px; font-size: 22px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 14px; }
        input { width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; margin-bottom: 16px; box-sizing: border-box; }
        input:focus { outline: none; border-color: #2563eb; }
        button { width: 100%; padding: 12px; background: #2563eb; color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; }
        button:hover { background: #1d4ed8; }
        .note { font-size: 12px; color: #94a3b8; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="box">
        <h1>&#127968; Установка ТСЖ "Наш Дом"</h1>
        <p style="color:#64748b; margin-bottom:20px;">Введите данные подключения к базе данных MySQL на Beget</p>
        <form method="POST">
            <label>Хост БД</label>
            <input type="text" name="db_host" value="localhost" required>

            <label>Имя базы данных</label>
            <input type="text" name="db_name" value="tszh_db" required>

            <label>Пользователь БД</label>
            <input type="text" name="db_user" value="" required placeholder="Имя пользователя MySQL">

            <label>Пароль БД</label>
            <input type="password" name="db_pass" value="" placeholder="Пароль MySQL">

            <button type="submit">Установить</button>
        </form>
        <div class="note">
            На Beget: зайдите в панель управления &rarr; MySQL &rarr; создайте базу данных, затем укажите данные выше.
        </div>
    </div>
</body>
</html>
