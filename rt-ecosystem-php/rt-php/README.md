# Экосистема коммерческого блока (Ростелеком, хакатон ДГТУ-26)

Прототип «Радара присутствия»: вход с двухфакторной аутентификацией, фильтры,
карточки, графики, таблица, экспорт в CSV.
Стек: PHP 8.3 (Apache), без базы данных. Данные читаются из data/ours.csv,
пользователи хранятся в data/users.json.

## Запуск на сервере
    git clone <repo> rt-ecosystem && cd rt-ecosystem
    chmod -R 777 data
    docker compose up -d --build
    docker compose exec -u www-data app php tools/create_user.php admin 'ВашПароль123'

Открыть http://IP_СЕРВЕРА, войти. При первом входе покажется QR-код: отсканируйте его
в приложении-аутентификаторе и введите код. Дальше при каждом входе нужен код из приложения.

Обновление: git pull && docker compose up -d --build

## Локальный запуск на ПК (Windows, без Docker)
    php tools/create_user.php admin 'Password12345'
    php -S localhost:8000 -t public tools/router.php
Открыть http://localhost:8000 (нужен PHP 8.3 с включённым расширением mbstring).

## Сброс пароля или 2FA (потерян телефон)
Повторно выполните create_user.php с тем же логином: пароль обновится, 2FA привяжется заново.

## Структура
    public/     корень сайта: index.php (экран), login.php (вход + 2FA), assets/, api/index.php
    src/        PHP-код: bootstrap, totp, users, data, industries
    tools/      create_user.php
    data/       ours.csv (в git), users.json (не в git)
    frontend/   зарезервировано под React (пока не используется)

## Определения
- Наш клиент: ИНН есть в data/ours.csv (выданная таблица, лист МОД1_Таб1).
- Отрасль: по классу ОКВЭД, таблица в src/industries.php (проверьте и поправьте).
