<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
session_boot();

if (is_authenticated()) {
    redirect('/');
}

$error  = '';
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string)($_POST['action'] ?? '') : '';
if ($action !== '') {
    csrf_check();
}

// Шаг 2 живёт 10 минут после правильного пароля
if (!empty($_SESSION['pending_user']) && time() - (int)($_SESSION['pending_at'] ?? 0) > 600) {
    unset($_SESSION['pending_user'], $_SESSION['pending_at'], $_SESSION['setup_secret']);
}

if ($action === 'demo') {
    csrf_check();
    session_regenerate_id(true);
    $_SESSION['auth_user'] = 'demo';
    redirect('/');
}

if ($action === 'cancel') {
    unset($_SESSION['pending_user'], $_SESSION['pending_at'], $_SESSION['setup_secret']);
    redirect('/login.php');
}

// ---- Шаг 1: логин и пароль ----
if ($action === 'password') {
    $u     = trim((string)($_POST['username'] ?? ''));
    $p     = (string)($_POST['password'] ?? '');
    $users = users_load();
    $rec   = $users[$u] ?? null;

    if ($rec && ($left = user_locked($rec)) > 0) {
        $error = 'Слишком много попыток. Подождите ' . (int)ceil($left / 60) . ' мин.';
    } elseif ($rec && password_verify($p, (string)$rec['hash'])) {
        session_regenerate_id(true);
        $_SESSION['pending_user'] = $u;
        $_SESSION['pending_at']   = time();
    } else {
        if ($rec) {
            user_register_fail($u);
        } else {
            password_verify($p, password_hash('x', PASSWORD_DEFAULT)); // одинаковое время ответа
        }
        $error = 'Неверный логин или пароль';
    }
}

// ---- Шаг 2: код из приложения ----
if ($action === 'code') {
    $u     = (string)($_SESSION['pending_user'] ?? '');
    $users = users_load();
    $rec   = $users[$u] ?? null;

    if (!$rec) {
        redirect('/login.php');
    }
    if (($left = user_locked($rec)) > 0) {
        $error = 'Слишком много попыток. Подождите ' . (int)ceil($left / 60) . ' мин.';
    } else {
        $secret = (string)($rec['totp_secret'] ?: ($_SESSION['setup_secret'] ?? ''));
        $code   = (string)preg_replace('/\D/', '', (string)($_POST['code'] ?? ''));
        $step   = null;
        if ($secret !== '' && totp_verify($secret, $code, (int)($rec['last_step'] ?? 0), $step)) {
            $users[$u]['totp_secret']  = $secret;     // при первой привязке сохраняем ключ
            $users[$u]['last_step']    = $step;
            $users[$u]['fails']        = 0;
            $users[$u]['locked_until'] = 0;
            users_save($users);
            session_regenerate_id(true);
            unset($_SESSION['pending_user'], $_SESSION['pending_at'], $_SESSION['setup_secret']);
            $_SESSION['auth_user'] = $u;
            redirect('/');
        }
        user_register_fail($u);
        $error = 'Неверный код. Проверьте время на телефоне и попробуйте снова.';
    }
}

// ---- Какой экран показывать ----
$stage   = 'password';
$pending = $_SESSION['pending_user'] ?? null;
if ($pending) {
    $rec = users_load()[$pending] ?? null;
    if ($rec) {
        $stage = !empty($rec['totp_secret']) ? 'verify' : 'setup';
        if ($stage === 'setup' && empty($_SESSION['setup_secret'])) {
            $_SESSION['setup_secret'] = totp_new_secret();
        }
    }
}
$secret = (string)($_SESSION['setup_secret'] ?? '');
?><!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Вход · Экосистема коммерческого блока</title>
  <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="auth-body">
  <main class="auth-card">
    <h1 class="brand">Экосистема<br>коммерческого блока</h1>

    <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>

    <?php if ($stage === 'password'): ?>
      <form method="post" autocomplete="on">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="password">
        <label>Логин <input name="username" autocomplete="username" required autofocus></label>
        <label>Пароль <input name="password" type="password" autocomplete="current-password" required></label>
        <button class="btn" type="submit">Продолжить</button>
      </form>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="demo">
        <button class="btn btn-outline" type="submit">Демонстрационная версия</button>
      </form>

    <?php else: ?>
      <?php if ($stage === 'setup'): ?>
        <p><b>Настройка двухфакторной защиты.</b> Откройте приложение-аутентификатор
           (Google Authenticator, Яндекс Ключ, Microsoft Authenticator), добавьте аккаунт
           по QR-коду или введите ключ вручную, затем введите 6-значный код.</p>
        <div id="qr" class="qr"></div>
        <p class="hint">Ключ вручную: <code><?= h($secret) ?></code> (16 символов, без пробелов, тип «по времени»)</p>
        <script>const OTP_URI = <?= json_encode(totp_uri((string)$pending, $secret), JSON_HEX_TAG) ?>;</script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
        <script>
          if (window.QRCode) { new QRCode(document.getElementById('qr'), {text: OTP_URI, width: 240, height: 240, correctLevel: QRCode.CorrectLevel.L}); }
        </script>
      <?php else: ?>
        <p>Введите 6-значный код из приложения-аутентификатора.</p>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="code">
        <label>Код <input name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7"
                          autocomplete="one-time-code" required autofocus></label>
        <button class="btn" type="submit">Войти</button>
      </form>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="cancel">
        <button class="link" type="submit">Назад</button>
      </form>
    <?php endif; ?>
  </main>
</body>
</html>
