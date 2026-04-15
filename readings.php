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

$tariffs = $pdo->query("SELECT * FROM tariffs ORDER BY name")->fetchAll();

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reading_date = date('Y-m-d');
    $period = date('Y-m');
    $total = 0;
    $bill_items = [];

    $pdo->beginTransaction();
    try {
        foreach ($tariffs as $tariff) {
            $current = floatval($_POST['current_' . $tariff['id']] ?? 0);
            if ($current <= 0) continue;

            $stmt = $pdo->prepare("
                SELECT current_value FROM meter_readings 
                WHERE apartment_id = ? AND tariff_id = ? 
                ORDER BY reading_date DESC, id DESC LIMIT 1
            ");
            $stmt->execute([$apartment['id'], $tariff['id']]);
            $last = $stmt->fetch();
            $previous = $last ? floatval($last['current_value']) : 0;

            if ($current < $previous) continue;

            $stmt = $pdo->prepare("
                INSERT INTO meter_readings (apartment_id, tariff_id, previous_value, current_value, reading_date) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$apartment['id'], $tariff['id'], $previous, $current, $reading_date]);
            $reading_id = $pdo->lastInsertId();

            $consumption = $current - $previous;
            $amount = round($consumption * $tariff['price_per_unit'], 2);
            $total += $amount;

            $bill_items[] = [
                'tariff_id' => $tariff['id'],
                'reading_id' => $reading_id,
                'quantity' => $consumption,
                'price' => $tariff['price_per_unit'],
                'amount' => $amount
            ];
        }

        if (count($bill_items) > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO bills (apartment_id, period, total_amount) VALUES (?, ?, ?)
            ");
            $stmt->execute([$apartment['id'], $period, $total]);
            $bill_id = $pdo->lastInsertId();

            foreach ($bill_items as $item) {
                $stmt = $pdo->prepare("
                    INSERT INTO bill_items (bill_id, tariff_id, meter_reading_id, quantity, price_per_unit, amount) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$bill_id, $item['tariff_id'], $item['reading_id'], $item['quantity'], $item['price'], $item['amount']]);
            }
        }

        $pdo->commit();
        redirect('readings.php?success=' . urlencode('Показания сохранены. Сумма к оплате: ' . number_format($total, 2, ',', ' ') . ' руб.'));
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Ошибка сохранения: ' . $e->getMessage();
    }
}

$last_readings = [];
foreach ($tariffs as $tariff) {
    $stmt = $pdo->prepare("
        SELECT current_value FROM meter_readings 
        WHERE apartment_id = ? AND tariff_id = ? 
        ORDER BY reading_date DESC, id DESC LIMIT 1
    ");
    $stmt->execute([$apartment['id'], $tariff['id']]);
    $last = $stmt->fetch();
    $last_readings[$tariff['id']] = $last ? $last['current_value'] : 0;
}

$stmt = $pdo->prepare("
    SELECT mr.*, t.name as tariff_name, t.unit, t.price_per_unit 
    FROM meter_readings mr 
    JOIN tariffs t ON t.id = mr.tariff_id 
    WHERE mr.apartment_id = ? 
    ORDER BY mr.reading_date DESC, mr.id DESC LIMIT 30
");
$stmt->execute([$apartment['id']]);
$history = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Показания счётчиков - ТСЖ</title>
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
                <a href="readings.php" class="active">
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
                <h1>Показания счётчиков</h1>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= escape($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= escape($error) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3>Ввод показаний</h3>
                </div>
                <form method="POST" action="" id="readingsForm">
                    <div class="readings-form">
                        <div class="reading-row" style="font-weight:600; font-size:12px; text-transform:uppercase; color:#374151;">
                            <div>Услуга</div>
                            <div>Предыдущее</div>
                            <div>Текущее</div>
                            <div style="text-align:right">Сумма</div>
                        </div>
                        <?php foreach ($tariffs as $tariff): ?>
                        <div class="reading-row">
                            <div>
                                <div class="reading-label"><?= escape($tariff['name']) ?></div>
                                <div class="reading-unit"><?= escape($tariff['unit']) ?></div>
                                <div class="reading-price"><?= number_format($tariff['price_per_unit'], 2, ',', ' ') ?> &#8381;/<?= escape($tariff['unit']) ?></div>
                            </div>
                            <div>
                                <input type="number" value="<?= $last_readings[$tariff['id']] ?>" readonly 
                                       style="background:#f1f5f9" id="prev_<?= $tariff['id'] ?>">
                            </div>
                            <div>
                                <input type="number" step="0.001" name="current_<?= $tariff['id'] ?>" 
                                       id="current_<?= $tariff['id'] ?>" 
                                       min="<?= $last_readings[$tariff['id']] ?>"
                                       placeholder="Введите показание"
                                       data-price="<?= $tariff['price_per_unit'] ?>"
                                       data-prev="<?= $last_readings[$tariff['id']] ?>"
                                       oninput="calculateRow(this)">
                            </div>
                            <div class="row-total" id="total_<?= $tariff['id'] ?>">0,00 &#8381;</div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="calc-result">
                        <div>Итого к оплате:</div>
                        <div class="total" id="grandTotal">0,00 &#8381;</div>
                        <div class="detail" id="grandDetail"></div>
                    </div>

                    <div class="modal-actions">
                        <button type="submit" class="btn btn-success">Сохранить показания</button>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>История показаний</h3>
                </div>
                <?php if (count($history) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Дата</th>
                            <th>Услуга</th>
                            <th>Предыдущее</th>
                            <th>Текущее</th>
                            <th>Расход</th>
                            <th>Сумма</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $r): ?>
                        <tr>
                            <td><?= date('d.m.Y', strtotime($r['reading_date'])) ?></td>
                            <td><?= escape($r['tariff_name']) ?></td>
                            <td><?= $r['previous_value'] ?></td>
                            <td><?= $r['current_value'] ?></td>
                            <td><?= round($r['current_value'] - $r['previous_value'], 3) ?> <?= escape($r['unit']) ?></td>
                            <td><strong><?= number_format(($r['current_value'] - $r['previous_value']) * $r['price_per_unit'], 2, ',', ' ') ?> &#8381;</strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">&#128201;</div>
                    <p>Показания ещё не подавались</p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script src="script.js"></script>
</body>
</html>
