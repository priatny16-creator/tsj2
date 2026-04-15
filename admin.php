<?php
require_once 'config.php';
if (!isLoggedIn() || !isAdmin()) redirect('index.php');

$section = $_GET['section'] ?? 'dashboard';
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

$total_apartments = $pdo->query("SELECT COUNT(*) FROM apartments")->fetchColumn();
$total_residents = $pdo->query("SELECT COUNT(*) FROM residents")->fetchColumn();
$total_bills = $pdo->query("SELECT COUNT(*) FROM bills")->fetchColumn();
$unpaid_sum = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM bills WHERE is_paid = 0")->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $login = trim($_POST['login'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = $_POST['role'] ?? 'resident';

        if ($login && $full_name && $password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO users (login, password, full_name, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$login, $hash, $full_name, $role]);
                redirect('admin.php?section=residents&success=' . urlencode('Пользователь добавлен'));
            } catch (PDOException $e) {
                $error = 'Ошибка: логин уже существует';
            }
        } else {
            $error = 'Заполните все поля';
        }
    }

    if ($action === 'add_apartment') {
        $number = trim($_POST['number'] ?? '');
        $floor = intval($_POST['floor'] ?? 0);
        $area = floatval($_POST['area'] ?? 0);
        $rooms = intval($_POST['rooms'] ?? 0);
        $owner_id = intval($_POST['owner_id'] ?? 0) ?: null;

        if ($number && $floor && $area && $rooms) {
            $stmt = $pdo->prepare("INSERT INTO apartments (number, floor, area, rooms, owner_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$number, $floor, $area, $rooms, $owner_id]);
            redirect('admin.php?section=apartments&success=' . urlencode('Квартира добавлена'));
        } else {
            $error = 'Заполните все обязательные поля';
        }
    }

    if ($action === 'add_resident') {
        $user_id_res = intval($_POST['user_id'] ?? 0);
        $apartment_id = intval($_POST['apartment_id'] ?? 0);
        $phone = trim($_POST['phone'] ?? '');
        $registered_at = $_POST['registered_at'] ?? date('Y-m-d');

        if ($user_id_res && $apartment_id) {
            $stmt = $pdo->prepare("INSERT INTO residents (user_id, apartment_id, phone, registered_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id_res, $apartment_id, $phone, $registered_at]);
            redirect('admin.php?section=residents&success=' . urlencode('Жилец привязан'));
        }
    }

    if ($action === 'update_tariff') {
        $tariff_id = intval($_POST['tariff_id'] ?? 0);
        $price = floatval($_POST['price_per_unit'] ?? 0);
        if ($tariff_id && $price > 0) {
            $stmt = $pdo->prepare("UPDATE tariffs SET price_per_unit = ? WHERE id = ?");
            $stmt->execute([$price, $tariff_id]);
            redirect('admin.php?section=tariffs&success=' . urlencode('Тариф обновлён'));
        }
    }

    if ($action === 'mark_paid') {
        $bill_id = intval($_POST['bill_id'] ?? 0);
        if ($bill_id) {
            $stmt = $pdo->prepare("UPDATE bills SET is_paid = 1, paid_at = NOW() WHERE id = ?");
            $stmt->execute([$bill_id]);
            redirect('admin.php?section=bills&success=' . urlencode('Счёт отмечен как оплаченный'));
        }
    }

    if ($action === 'delete_user') {
        $del_id = intval($_POST['user_id'] ?? 0);
        if ($del_id && $del_id != $_SESSION['user_id']) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$del_id]);
            redirect('admin.php?section=residents&success=' . urlencode('Пользователь удалён'));
        }
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY full_name")->fetchAll();
$apartments = $pdo->query("
    SELECT a.*, u.full_name as owner_name 
    FROM apartments a 
    LEFT JOIN users u ON u.id = a.owner_id 
    ORDER BY CAST(a.number AS UNSIGNED)
")->fetchAll();
$residents_list = $pdo->query("
    SELECT r.*, u.full_name, u.login, a.number as apt_number 
    FROM residents r 
    JOIN users u ON u.id = r.user_id 
    JOIN apartments a ON a.id = r.apartment_id 
    ORDER BY u.full_name
")->fetchAll();
$tariffs = $pdo->query("SELECT * FROM tariffs ORDER BY name")->fetchAll();
$bills_list = $pdo->query("
    SELECT b.*, a.number as apt_number 
    FROM bills b 
    JOIN apartments a ON a.id = b.apartment_id 
    ORDER BY b.period DESC, a.number
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель - ТСЖ</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>&#127968; ТСЖ "Наш Дом"</h2>
                <p>Администратор</p>
            </div>
            <nav class="sidebar-nav">
                <a href="admin.php?section=dashboard" class="<?= $section === 'dashboard' ? 'active' : '' ?>">
                    <span class="icon">&#128200;</span> Главная
                </a>
                <a href="admin.php?section=apartments" class="<?= $section === 'apartments' ? 'active' : '' ?>">
                    <span class="icon">&#127970;</span> Квартиры
                </a>
                <a href="admin.php?section=residents" class="<?= $section === 'residents' ? 'active' : '' ?>">
                    <span class="icon">&#128101;</span> Жильцы
                </a>
                <a href="admin.php?section=bills" class="<?= $section === 'bills' ? 'active' : '' ?>">
                    <span class="icon">&#128179;</span> Счета ЖКХ
                </a>
                <a href="admin.php?section=tariffs" class="<?= $section === 'tariffs' ? 'active' : '' ?>">
                    <span class="icon">&#128176;</span> Тарифы
                </a>
                <a href="logout.php">
                    <span class="icon">&#128682;</span> Выход
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <?php if ($success): ?>
                <div class="alert alert-success"><?= escape($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= escape($error) ?></div>
            <?php endif; ?>

            <?php if ($section === 'dashboard'): ?>
            <div class="page-header"><h1>Панель управления</h1></div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">&#127970;</div>
                    <div class="stat-value"><?= $total_apartments ?></div>
                    <div class="stat-label">Квартир</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">&#128101;</div>
                    <div class="stat-value"><?= $total_residents ?></div>
                    <div class="stat-label">Жильцов</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">&#128179;</div>
                    <div class="stat-value"><?= $total_bills ?></div>
                    <div class="stat-label">Счетов</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">&#128176;</div>
                    <div class="stat-value"><?= number_format($unpaid_sum, 0, ',', ' ') ?> &#8381;</div>
                    <div class="stat-label">Задолженность</div>
                </div>
            </div>

            <div class="card">
                <h3 style="margin-bottom:16px;">Последние счета</h3>
                <?php $recent = array_slice($bills_list, 0, 10); ?>
                <?php if (count($recent) > 0): ?>
                <table>
                    <thead>
                        <tr><th>Период</th><th>Квартира</th><th>Сумма</th><th>Статус</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $b): ?>
                        <tr>
                            <td><?= escape($b['period']) ?></td>
                            <td>Кв. <?= escape($b['apt_number']) ?></td>
                            <td><strong><?= number_format($b['total_amount'], 2, ',', ' ') ?> &#8381;</strong></td>
                            <td>
                                <?php if ($b['is_paid']): ?>
                                    <span class="badge badge-success">Оплачен</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Не оплачен</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state"><p>Счетов пока нет</p></div>
                <?php endif; ?>
            </div>

            <?php elseif ($section === 'apartments'): ?>
            <div class="page-header">
                <h1>Квартиры</h1>
                <button class="btn btn-primary" onclick="openModal('addApartment')">Добавить квартиру</button>
            </div>
            <div class="card">
                <table>
                    <thead>
                        <tr><th>Номер</th><th>Этаж</th><th>Площадь</th><th>Комнат</th><th>Владелец</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($apartments as $apt): ?>
                        <tr>
                            <td><strong>Кв. <?= escape($apt['number']) ?></strong></td>
                            <td><?= $apt['floor'] ?></td>
                            <td><?= $apt['area'] ?> м²</td>
                            <td><?= $apt['rooms'] ?></td>
                            <td><?= $apt['owner_name'] ? escape($apt['owner_name']) : '<span style="color:#94a3b8">Не указан</span>' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="modal-overlay" id="addApartment">
                <div class="modal">
                    <h2>Добавить квартиру</h2>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add_apartment">
                        <div class="form-group">
                            <label>Номер квартиры</label>
                            <input type="text" name="number" required>
                        </div>
                        <div class="form-group">
                            <label>Этаж</label>
                            <input type="number" name="floor" required min="1">
                        </div>
                        <div class="form-group">
                            <label>Площадь (м²)</label>
                            <input type="number" name="area" step="0.01" required min="1">
                        </div>
                        <div class="form-group">
                            <label>Количество комнат</label>
                            <input type="number" name="rooms" required min="1">
                        </div>
                        <div class="form-group">
                            <label>Владелец</label>
                            <select name="owner_id">
                                <option value="">Не указан</option>
                                <?php foreach ($users as $u): ?>
                                    <?php if ($u['role'] === 'resident'): ?>
                                    <option value="<?= $u['id'] ?>"><?= escape($u['full_name']) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('addApartment')">Отмена</button>
                            <button type="submit" class="btn btn-primary">Добавить</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php elseif ($section === 'residents'): ?>
            <div class="page-header">
                <h1>Жильцы</h1>
                <div>
                    <button class="btn btn-primary" onclick="openModal('addUser')">Добавить пользователя</button>
                    <button class="btn btn-success" onclick="openModal('addResident')">Привязать жильца</button>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3>Пользователи</h3></div>
                <table>
                    <thead>
                        <tr><th>ФИО</th><th>Логин</th><th>Роль</th><th>Действия</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><strong><?= escape($u['full_name']) ?></strong></td>
                            <td><?= escape($u['login']) ?></td>
                            <td>
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="badge badge-danger">Админ</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Жилец</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" action="" style="display:inline" onsubmit="return confirm('Удалить пользователя?')">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Удалить</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <div class="card-header"><h3>Привязка жильцов к квартирам</h3></div>
                <?php if (count($residents_list) > 0): ?>
                <table>
                    <thead>
                        <tr><th>ФИО</th><th>Логин</th><th>Квартира</th><th>Телефон</th><th>Дата регистрации</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($residents_list as $r): ?>
                        <tr>
                            <td><strong><?= escape($r['full_name']) ?></strong></td>
                            <td><?= escape($r['login']) ?></td>
                            <td>Кв. <?= escape($r['apt_number']) ?></td>
                            <td><?= escape($r['phone'] ?? '-') ?></td>
                            <td><?= date('d.m.Y', strtotime($r['registered_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state"><p>Жильцы ещё не привязаны</p></div>
                <?php endif; ?>
            </div>

            <div class="modal-overlay" id="addUser">
                <div class="modal">
                    <h2>Добавить пользователя</h2>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add_user">
                        <div class="form-group">
                            <label>ФИО</label>
                            <input type="text" name="full_name" required>
                        </div>
                        <div class="form-group">
                            <label>Логин</label>
                            <input type="text" name="login" required>
                        </div>
                        <div class="form-group">
                            <label>Пароль</label>
                            <input type="password" name="password" required minlength="6">
                        </div>
                        <div class="form-group">
                            <label>Роль</label>
                            <select name="role">
                                <option value="resident">Жилец</option>
                                <option value="admin">Администратор</option>
                            </select>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('addUser')">Отмена</button>
                            <button type="submit" class="btn btn-primary">Добавить</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="modal-overlay" id="addResident">
                <div class="modal">
                    <h2>Привязать жильца к квартире</h2>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add_resident">
                        <div class="form-group">
                            <label>Пользователь</label>
                            <select name="user_id" required>
                                <option value="">Выберите</option>
                                <?php foreach ($users as $u): ?>
                                    <?php if ($u['role'] === 'resident'): ?>
                                    <option value="<?= $u['id'] ?>"><?= escape($u['full_name']) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Квартира</label>
                            <select name="apartment_id" required>
                                <option value="">Выберите</option>
                                <?php foreach ($apartments as $apt): ?>
                                <option value="<?= $apt['id'] ?>">Кв. <?= escape($apt['number']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Телефон</label>
                            <input type="tel" name="phone" placeholder="+7 (___) ___-__-__">
                        </div>
                        <div class="form-group">
                            <label>Дата регистрации</label>
                            <input type="date" name="registered_at" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('addResident')">Отмена</button>
                            <button type="submit" class="btn btn-success">Привязать</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php elseif ($section === 'bills'): ?>
            <div class="page-header"><h1>Счета ЖКХ</h1></div>
            <div class="card">
                <?php if (count($bills_list) > 0): ?>
                <table>
                    <thead>
                        <tr><th>Период</th><th>Квартира</th><th>Сумма</th><th>Статус</th><th>Действия</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bills_list as $b): ?>
                        <tr>
                            <td><?= escape($b['period']) ?></td>
                            <td>Кв. <?= escape($b['apt_number']) ?></td>
                            <td><strong><?= number_format($b['total_amount'], 2, ',', ' ') ?> &#8381;</strong></td>
                            <td>
                                <?php if ($b['is_paid']): ?>
                                    <span class="badge badge-success">Оплачен</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Не оплачен</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$b['is_paid']): ?>
                                <form method="POST" action="" style="display:inline">
                                    <input type="hidden" name="action" value="mark_paid">
                                    <input type="hidden" name="bill_id" value="<?= $b['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success">Оплачен</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state"><p>Счетов пока нет</p></div>
                <?php endif; ?>
            </div>

            <?php elseif ($section === 'tariffs'): ?>
            <div class="page-header"><h1>Тарифы</h1></div>
            <div class="card">
                <table>
                    <thead>
                        <tr><th>Услуга</th><th>Единица</th><th>Цена за ед.</th><th>Обновлено</th><th>Действия</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tariffs as $t): ?>
                        <tr>
                            <td><strong><?= escape($t['name']) ?></strong></td>
                            <td><?= escape($t['unit']) ?></td>
                            <td><?= number_format($t['price_per_unit'], 4, ',', ' ') ?> &#8381;</td>
                            <td><?= date('d.m.Y', strtotime($t['updated_at'])) ?></td>
                            <td>
                                <form method="POST" action="" style="display:flex; gap:8px; align-items:center;">
                                    <input type="hidden" name="action" value="update_tariff">
                                    <input type="hidden" name="tariff_id" value="<?= $t['id'] ?>">
                                    <input type="number" name="price_per_unit" step="0.0001" value="<?= $t['price_per_unit'] ?>" 
                                           style="width:120px; padding:6px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:13px;">
                                    <button type="submit" class="btn btn-sm btn-primary">Сохранить</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </main>
    </div>
    <script src="script.js"></script>
</body>
</html>
