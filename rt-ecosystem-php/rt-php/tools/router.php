<?php
declare(strict_types=1);

/**
 * Роутер для встроенного сервера PHP (локальный запуск на ПК).
 * На боевом сервере эту роль выполняет public/.htaccess.
 * Запуск:  php -S localhost:8000 -t public tools/router.php
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

if (str_starts_with($path, '/api/') && $path !== '/api/index.php') {
    require __DIR__ . '/../public/api/index.php';   // все /api/... идут в один файл
    return true;
}
return false;   // остальное (страницы, css, js) отдаём как есть
