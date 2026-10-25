<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
session_boot();

if (!is_authenticated() || (string)($_SESSION['auth_user'] ?? '') !== 'admin') {
    redirect('/login.php');
}

$error = '';
$msg   = '';
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string)($_POST['action'] ?? '') : '';
if ($action !== '') { csrf_check(); }

if ($action === 'add_user') {
    $u = trim((string)($_POST['username'] ?? ''));
    $p = (string)($_POST['password'] ?? '');
    if ($u === '' || strlen($p) < 8) {
        $error = 'Логин обязателен, пароль от 8 символов.';
    } else {
        $users = users_load();
        $users[$u] = [
            'hash'         => password_hash($p, PASSWORD_DEFAULT),
            'totp_secret'  => null,
            'last_step'    => 0,
            'fails'        => 0,
            'locked_until' => 0,
        ];
        users_save($users);
        $msg = "Пользователь «$u» создан.";
    }
}

if ($action === 'reset_2fa') {
    $u = (string)($_POST['username'] ?? '');
    $users = users_load();
    if (isset($users[$u])) {
        $users[$u]['totp_secret']  = null;
        $users[$u]['last_step']    = 0;
        $users[$u]['fails']        = 0;
        $users[$u]['locked_until'] = 0;
        users_save($users);
        $msg = "2FA для «$u» сброшена.";
    }
}

if ($action === 'delete_user') {
    $u = (string)($_POST['username'] ?? '');
    if ($u !== 'admin') {
        $users = users_load();
        unset($users[$u]);
        users_save($users);
        $msg = "Пользователь «$u» удалён.";
    } else {
        $error = 'Нельзя удалить admin.';
    }
}

$users = users_load();
?><!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Админ-панель</title>
  <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<main class="auth-card" style="max-width:720px;margin:40px auto">
  <h1>Админ-панель</h1>
  <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
  <?php if ($msg): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>

  <h2>Пользователи</h2>
  <table style="width:100%;border-collapse:collapse">
    <tr><th>Логин</th><th>2FA</th><th>Действия</th></tr>
    <?php foreach ($users as $u => $r): ?>
    <tr>
      <td><?= h($u) ?></td>
      <td><?= !empty($r['totp_secret']) ? 'привязана' : 'не привязана' ?></td>
      <td>
        <form method="post" style="display:inline">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="reset_2fa">
          <input type="hidden" name="username" value="<?= h($u) ?>">
          <button class="link" type="submit">Сбросить 2FA</button>
        </form>
        <?php if ($u !== 'admin'): ?>
        <form method="post" style="display:inline">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="delete_user">
          <input type="hidden" name="username" value="<?= h($u) ?>">
          <button class="link" type="submit">Удалить</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>

  <h2>Добавить пользователя</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="add_user">
    <label>Логин <input name="username" required></label>
    <label>Пароль <input name="password" type="password" required></label>
    <button class="btn" type="submit">Создать</button>
  </form>

  <h2>Справочные команды (Docker)</h2>
  <ul style="font-size:13px;line-height:1.8">
    <li><code>docker compose ps</code> — статус контейнеров</li>
    <li><code>docker compose logs -f app</code> — логи приложения в реальном времени</li>
    <li><code>docker compose up -d --build</code> — пересобрать и запустить</li>
    <li><code>docker compose down</code> — остановить и удалить контейнеры</li>
    <li><code>docker compose exec -u www-data app php tools/create_user.php ЛОГИН 'ПАРОЛЬ'</code> — создать/сбросить пользователя</li>
    <li><code>chmod -R 777 data</code> — права на папку данных</li>
  </ul>

  <p><a href="/">На главную</a> · <a href="/logout.php">Выйти</a></p>
</main>
</body>
</html>
