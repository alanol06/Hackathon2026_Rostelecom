<?php
declare(strict_types=1);

/**
 * Создать пользователя (или сбросить пароль и 2FA существующему):
 *   docker compose exec -u www-data app php tools/create_user.php ЛОГИН 'ПАРОЛЬ'
 * Двухфакторный ключ привяжется при первом входе (QR-код на экране).
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require __DIR__ . '/../src/bootstrap.php';

$login = $argv[1] ?? '';
$pass  = $argv[2] ?? '';
if ($login === '' || $pass === '') {
    fwrite(STDERR, "Использование: php tools/create_user.php ЛОГИН 'ПАРОЛЬ'\n");
    exit(1);
}
if (strlen($pass) < 8) {
    fwrite(STDERR, "Пароль должен быть не короче 8 символов.\n");
    exit(1);
}

$users   = users_load();
$existed = isset($users[$login]);
$users[$login] = [
    'hash'         => password_hash($pass, PASSWORD_DEFAULT),
    'totp_secret'  => null,      // привяжется при первом входе
    'last_step'    => 0,
    'fails'        => 0,
    'locked_until' => 0,
];
users_save($users);
echo $existed ? "Пользователь «$login» обновлён (пароль и 2FA сброшены).\n"
              : "Пользователь «$login» создан. 2FA привяжется при первом входе.\n";
