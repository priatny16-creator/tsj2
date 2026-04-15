<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('index.php');
if (isAdmin()) redirect('admin.php');

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT a.*, r.phone, r.registered_at 
    FROM apartments a 
    JOIN residents r ON r.apartment_id = a.id 
    WHERE r.user_id = ?
");
$stmt->execute([$user_id]);
$apartment = $stmt->fetch();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_phone') {
        $phone = trim($_POST['phone'] ?? '');
        $stmt = $pdo->prepare("UPDATE residents SET phone = ? WHERE user_id = ?");
        $stmt->execute([$phone, $user_id]);
        $success = 'Телефон обновлён';
        $apartment['phone'] = $phone;
    }

    if ($action === 'change_password') {
        $old_pass = $_POST['old_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($old_pass, $user['password'])) {
            $error = 'Неверный текущий пароль';
        } elseif (strlen($new_pass) < 6) {
            $error = 'Новый пароль должен быть не менее 6 символов';
        } elseif ($new_pass !== $confirm) {
            $error = 'Пароли не совпадают';
        } else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $user_id]);
            $success = 'Пароль изменён';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль - ТСЖ</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>&#127968; ТСЖ "Наш Дом"</h2>
                <p><?= escape($_SESSION['full_name']) ?></p>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php">
                    <span class="icon">&#128200;</span> Главная
                </a>
                <a href="readings.php">
                    <span class="icon">&#128201;</span> Показания счётчиков
                </a>
                <a href="bills.php">
                    <span class="icon">&#128179;</span> Счета ЖКХ
                </a>
                <a href="profile.php" class="active">
                    <span class="icon">&#128100;</span> Профиль
                </a>
                <a href="logout.php">
                    <span class="icon">&#128682;</span> Выход
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h1>Профиль</h1>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= escape($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= escape($error) ?></div>
            <?php endif; ?>

            <div class="card">
                <h3 style="margin-bottom:16px;">Личные данные</h3>
                <table>
                    <tr><td style="width:200px; color:#64748b;">ФИО</td><td><strong><?= escape($user['full_name']) ?></strong></td></tr>
                    <tr><td style="color:#64748b;">Логин</td><td><?= escape($user['login']) ?></td></tr>
                    <?php if ($apartment): ?>
                    <tr><td style="color:#64748b;">Квартира</td><td>№ <?= escape($apartment['number']) ?>, <?= $apartment['floor'] ?> этаж</td></tr>
                    <tr><td style="color:#64748b;">Площадь</td><td><?= $apartment['area'] ?> м²</td></tr>
                    <tr><td style="color:#64748b;">Комнат</td><td><?= $apartment['rooms'] ?></td></tr>
                    <tr><td style="color:#64748b;">Дата регистрации</td><td><?= date('d.m.Y', strtotime($apartment['registered_at'])) ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>

            <?php if ($apartment): ?>
            <div class="card">
                <h3 style="margin-bottom:16px;">Контактный телефон</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_phone">
                    <div class="form-group">
                        <label for="phone">Телефон</label>
                        <input type="tel" id="phone" name="phone" value="<?= escape($apartment['phone'] ?? '') ?>" placeholder="+7 (___) ___-__-__">
                    </div>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </form>
            </div>
            <?php endif; ?>

            <div class="card">
                <h3 style="margin-bottom:16px;">Сменить пароль</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label for="old_password">Текущий пароль</label>
                        <input type="password" id="old_password" name="old_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">Новый пароль</label>
                        <input type="password" id="new_password" name="new_password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Подтвердите пароль</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Изменить пароль</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
