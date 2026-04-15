<?php
require_once 'config.php';

if (isLoggedIn()) {
    redirect(isAdmin() ? 'admin.php' : 'dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($login && $password) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login'] = $user['login'];
            redirect($user['role'] === 'admin' ? 'admin.php' : 'dashboard.php');
        } else {
            $error = 'Неверный логин или пароль';
        }
    } else {
        $error = 'Заполните все поля';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет ТСЖ</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <div class="logo">&#127968;</div>
                <h1>Личный кабинет</h1>
                <p>ТСЖ "Наш Дом"</p>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= escape($error) ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="login">Логин</label>
                    <input type="text" id="login" name="login" required placeholder="Введите логин">
                </div>
                <div class="form-group">
                    <label for="password">Пароль</label>
                    <input type="password" id="password" name="password" required placeholder="Введите пароль">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Войти</button>
            </form>
            <div class="login-footer">
                <p>Демо-доступ: <strong>admin</strong> / <strong>admin123</strong></p>
                <p>Жилец: <strong>ivanov</strong> / <strong>admin123</strong></p>
            </div>
        </div>
    </div>
</body>
</html>
