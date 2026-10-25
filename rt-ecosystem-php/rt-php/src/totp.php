<?php
declare(strict_types=1);

/**
 * TOTP (RFC 6238) без внешних библиотек: коды из Google Authenticator,
 * Яндекс Ключа, Microsoft Authenticator и т.п.
 * Алгоритм сверен с тестовыми значениями из стандарта.
 */

const B32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function base32_encode_str(string $bin): string
{
    $bits = '';
    foreach (str_split($bin) as $c) {
        $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= B32_ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
    }
    return $out;
}

function base32_decode_str(string $s): string
{
    $s = strtoupper((string)preg_replace('/[^A-Za-z2-7]/', '', $s));
    if ($s === '') {
        return '';
    }
    $bits = '';
    foreach (str_split($s) as $ch) {
        $bits .= str_pad(decbin(strpos(B32_ALPHABET, $ch)), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

/**
 * Новый секрет: 10 случайных байт (80 бит) = ровно 16 символов base32.
 * Короткий ключ удобно вводить вручную: многие приложения ограничивают длину.
 * Для боевой системы лучше 20 байт (32 символа). Старые 32-символьные ключи работают.
 */
function totp_new_secret(): string
{
    return base32_encode_str(random_bytes(10));
}

/** 6-значный код для заданного 30-секундного шага. */
function totp_code(string $secret, int $step): string
{
    $key  = base32_decode_str($secret);
    $hash = hash_hmac('sha1', pack('N2', 0, $step), $key, true);
    $o    = ord($hash[19]) & 0x0F;
    $n    = ((ord($hash[$o]) & 0x7F) << 24)
          | (ord($hash[$o + 1]) << 16)
          | (ord($hash[$o + 2]) << 8)
          |  ord($hash[$o + 3]);
    return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
}

/**
 * Проверка кода. Допускаем соседние шаги (±30 с) на случай разницы часов,
 * но не принимаем шаг, который уже использовали (защита от повторного ввода).
 */
function totp_verify(string $secret, string $code, int $lastStep, ?int &$matchedStep = null): bool
{
    if (!preg_match('/^\d{6}$/', $code)) {
        return false;
    }
    $now = intdiv(time(), 30);
    for ($s = $now - 1; $s <= $now + 1; $s++) {
        if ($s <= $lastStep) {
            continue;
        }
        if (hash_equals(totp_code($secret, $s), $code)) {
            $matchedStep = $s;
            return true;
        }
    }
    return false;
}

/**
 * Ссылка otpauth:// для QR-кода. Двоеточие между сервисом и логином оставляем
 * обычным (не %3A): так требует формат Google Authenticator, и его понимают все приложения.
 */
function totp_uri(string $user, string $secret): string
{
    return 'otpauth://totp/' . rawurlencode('RT-Ecosystem') . ':' . rawurlencode($user)
         . '?secret=' . $secret . '&issuer=' . rawurlencode('RT-Ecosystem');
}
