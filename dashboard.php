<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('index.php');
if (isAdmin()) redirect('admin.php');

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT a.*, r.phone, r.registered_at 
    FROM apartments a 
    JOIN residents r ON r.apartment_id = a.id 
    WHERE r.user_id = ?
");
$stmt->execute([$user_id]);
$apartment = $stmt->fetch();

$bills = [];
$readings = [];
if ($apartment) {
    $stmt = $pdo->prepare("SELECT * FROM bills WHERE apartment_id = ? ORDER BY period DESC LIMIT 12");
    $stmt->execute([$apartment['id']]);
    $bills = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT mr.*, t.name as tariff_name, t.unit, t.price_per_unit 
        FROM meter_readings mr 
        JOIN tariffs t ON t.id = mr.tariff_id 
        WHERE mr.apartment_id = ? 
        ORDER BY mr.reading_date DESC LIMIT 20
    ");
    $stmt->execute([$apartment['id']]);
    $readings = $stmt->fetchAll();
}

$tariffs = $pdo->query("SELECT * FROM tariffs ORDER BY name")->fetchAll();

$unpaid = 0;
foreach ($bills as $b) {
    if (!$b['is_paid']) $unpaid += $b['total_amount'];
}

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет - ТСЖ</title>
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
                <a href="dashboard.php" class="active">
                    <span class="icon">&#128200;</span> Главная
                </a>
                <a href="readings.php">
                    <span class="icon">&#128201;</span> Показания счётчиков
                </a>
                <a href="bills.php">
                    <span class="icon">&#128179;</span> Счета ЖКХ
                </a>
                <a href="profile.php">
                    <span class="icon">&#128100;</span> Профиль
                </a>
                <a href="logout.php">
                    <span class="icon">&#128682;</span> Выход
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h1>Главная</h1>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= escape($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= escape($error) ?></div>
            <?php endif; ?>

            <?php if ($apartment): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">&#127970;</div>
                    <div class="stat-value">Кв. <?= escape($apartment['number']) ?></div>
                    <div class="stat-label"><?= $apartment['rooms'] ?> комн., <?= $apartment['area'] ?> м², <?= $apartment['floor'] ?> этаж</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">&#128176;</div>
                    <div class="stat-value"><?= number_format($unpaid, 2, ',', ' ') ?> &#8381;</div>
                    <div class="stat-label">Задолженность</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">&#128201;</div>
                    <div class="stat-value"><?= count($readings) ?></div>
                    <div class="stat-label">Показаний подано</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">&#128179;</div>
                    <div class="stat-value"><?= count($bills) ?></div>
                    <div class="stat-label">Счетов</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Последние счета</h3>
                    <a href="bills.php" class="btn btn-sm btn-primary">Все счета</a>
                </div>
                <?php if (count($bills) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Период</th>
                            <th>Сумма</th>
                            <th>Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($bills, 0, 5) as $bill): ?>
                        <tr>
                            <td><?= escape($bill['period']) ?></td>
                            <td><strong><?= number_format($bill['total_amount'], 2, ',', ' ') ?> &#8381;</strong></td>
                            <td>
                                <?php if ($bill['is_paid']): ?>
                                    <span class="badge badge-success">Оплачен</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Не оплачен</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">&#128179;</div>
                    <p>Счетов пока нет</p>
                </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Текущие тарифы</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Услуга</th>
                            <th>Единица</th>
                            <th>Цена</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tariffs as $t): ?>
                        <tr>
                            <td><?= escape($t['name']) ?></td>
                            <td><?= escape($t['unit']) ?></td>
                            <td><strong><?= number_format($t['price_per_unit'], 2, ',', ' ') ?> &#8381;</strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="empty-state">
                    <div class="empty-icon">&#127970;</div>
                    <p>Квартира не привязана к вашему аккаунту. Обратитесь к администратору.</p>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
