<?php
declare(strict_types=1);

/** Пользователи хранятся в data/users.json (без базы данных). */

function users_path(): string
{
    return DATA_DIR . '/users.json';
}

function users_load(): array
{
    $p = users_path();
    if (!is_file($p)) {
        return [];
    }
    $j = json_decode((string)file_get_contents($p), true);
    return is_array($j) ? $j : [];
}

function users_save(array $users): void
{
    file_put_contents(
        users_path(),
        json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

/** Сколько секунд осталось до разблокировки (0, если не заблокирован). */
function user_locked(array $rec): int
{
    $left = (int)($rec['locked_until'] ?? 0) - time();
    return $left > 0 ? $left : 0;
}

/** Неудачная попытка: после 5 подряд блокируем учётную запись на 5 минут. */
function user_register_fail(string $u): void
{
    $users = users_load();
    if (!isset($users[$u])) {
        return;
    }
    $users[$u]['fails'] = (int)($users[$u]['fails'] ?? 0) + 1;
    if ($users[$u]['fails'] >= 5) {
        $users[$u]['locked_until'] = time() + 300;
        $users[$u]['fails'] = 0;
    }
    users_save($users);
}
