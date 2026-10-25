<?php
declare(strict_types=1);

// Ошибки пишем в лог сервера, а не на страницу (иначе они испортят JSON и покажут лишнее).
ini_set('display_errors', '0');
error_reporting(E_ALL);

define('ROOT', dirname(__DIR__));
define('DATA_DIR', ROOT . '/data');

require_once ROOT . '/src/industries.php';
require_once ROOT . '/src/totp.php';
require_once ROOT . '/src/users.php';
require_once ROOT . '/src/geo.php';
require_once ROOT . '/src/data.php';
require_once ROOT . '/src/modules.php';

function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('rtsid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,        // JavaScript не видит cookie сессии
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

function is_authenticated(): bool
{
    session_boot();
    return !empty($_SESSION['auth_user']);
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

/** Экранирование текста для вставки в HTML. */
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** CSRF-токен: защита форм от отправки с чужого сайта. */
function csrf_token(): string
{
    session_boot();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    session_boot();
    if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Ошибка формы. Обновите страницу и повторите.');
    }
}
