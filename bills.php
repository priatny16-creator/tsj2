<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('index.php');
if (isAdmin()) redirect('admin.php');

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT a.* FROM apartments a 
    JOIN residents r ON r.apartment_id = a.id 
    WHERE r.user_id = ?
");
$stmt->execute([$user_id]);
$apartment = $stmt->fetch();

if (!$apartment) {
    redirect('dashboard.php?error=' . urlencode('Квартира не привязана'));
}

$stmt = $pdo->prepare("SELECT * FROM bills WHERE apartment_id = ? ORDER BY period DESC");
$stmt->execute([$apartment['id']]);
$bills = $stmt->fetchAll();

$bill_details = [];
if (isset($_GET['view'])) {
    $bill_id = intval($_GET['view']);
    $stmt = $pdo->prepare("
        SELECT bi.*, t.name as tariff_name, t.unit 
        FROM bill_items bi 
        JOIN tariffs t ON t.id = bi.tariff_id 
        WHERE bi.bill_id = ?
    ");
    $stmt->execute([$bill_id]);
    $bill_details = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM bills WHERE id = ? AND apartment_id = ?");
    $stmt->execute([$bill_id, $apartment['id']]);
    $current_bill = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Счета ЖКХ - ТСЖ</title>
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
                <a href="bills.php" class="active">
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
                <h1>Счета ЖКХ</h1>
            </div>

            <?php if (isset($current_bill) && $current_bill): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Счёт за <?= escape($current_bill['period']) ?></h3>
                    <a href="bills.php" class="btn btn-sm btn-secondary">Назад</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Услуга</th>
                            <th>Расход</th>
                            <th>Цена за ед.</th>
                            <th>Сумма</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bill_details as $item): ?>
                        <tr>
                            <td><?= escape($item['tariff_name']) ?></td>
                            <td><?= $item['quantity'] ?> <?= escape($item['unit']) ?></td>
                            <td><?= number_format($item['price_per_unit'], 2, ',', ' ') ?> &#8381;</td>
                            <td><strong><?= number_format($item['amount'], 2, ',', ' ') ?> &#8381;</strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="text-align:right; font-weight:600;">Итого:</td>
                            <td><strong style="font-size:18px; color:#2563eb;"><?= number_format($current_bill['total_amount'], 2, ',', ' ') ?> &#8381;</strong></td>
                        </tr>
                    </tfoot>
                </table>
                <div style="margin-top:16px;">
                    Статус: 
                    <?php if ($current_bill['is_paid']): ?>
                        <span class="badge badge-success">Оплачен <?= $current_bill['paid_at'] ? date('d.m.Y', strtotime($current_bill['paid_at'])) : '' ?></span>
                    <?php else: ?>
                        <span class="badge badge-warning">Не оплачен</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3>Все счета</h3>
                </div>
                <?php if (count($bills) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Период</th>
                            <th>Сумма</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bills as $bill): ?>
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
                            <td>
                                <a href="bills.php?view=<?= $bill['id'] ?>" class="btn btn-sm btn-primary">Подробнее</a>
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
        </main>
    </div>
</body>
</html>
