# Support Chat

Автономный веб-чат поддержки.

Проект содержит PHP-версию для сервера и Node.js dev-сервер для локальной разработки без установленного PHP.

## Локальный запуск

Требуется Node.js 18+.

```bash
npm start
```

После запуска:

- панель поддержки: `http://localhost:8080/index.php`
- страница клиента: `http://localhost:8080/support.php`
- ключ доступа по умолчанию: `admin`

Локальный запуск с приемом сообщений из Telegram через polling:

```bash
npm run start:telegram
```

Для этого в `.env` должен быть указан `TELEGRAM_BOT_TOKEN`.

Чтобы изменить порт:

```bash
$env:PORT=8090; npm start
```

Чтобы задать свой ключ доступа, создайте `.env`:

```env
SUPPORT_ADMIN_TOKEN=my-secret-token
TELEGRAM_BOT_TOKEN=put-telegram-bot-token-here
TELEGRAM_WEBHOOK_SECRET=change-me-webhook-secret
```

Локальные данные сохраняются в `storage/local-data.json`.

## Как проверить локально

1. Запустите `npm start`.
2. Откройте `http://localhost:8080/support.php`.
3. Напишите сообщение от клиента.
4. Откройте `http://localhost:8080/index.php`.
5. Введите ключ `admin`.
6. Выберите диалог с меткой `Сайт`.
7. Ответьте из панели поддержки.
8. Вернитесь на страницу клиента: ответ появится автоматически.

## Проверка Telegram локально

Вариант 1: реальный бот через polling.

1. Создайте `.env`.
2. Добавьте `TELEGRAM_BOT_TOKEN`.
3. Запустите `npm run start:telegram`.
4. Напишите сообщение боту в Telegram.
5. Откройте панель поддержки: появится диалог с меткой `Telegram`.

Вариант 2: тестовый webhook-запрос без реального Telegram.

```bash
Invoke-RestMethod -Method Post http://localhost:8080/telegram-webhook.php `
  -ContentType "application/json" `
  -Body '{"message":{"message_id":1,"text":"Привет из Telegram","chat":{"id":12345,"first_name":"Test","username":"test_user"}}}'
```

После этого в панели появится диалог с меткой `Telegram`.

## Установка на сервер

1. Скопируйте папку проекта на отдельный сайт или поддомен.
2. Создайте `.env` из `.env.example`.
3. Укажите секреты:
   - `SUPPORT_ADMIN_TOKEN` - пароль доступа к панели поддержки.
   - `TELEGRAM_BOT_TOKEN` - токен отдельного Telegram-бота.
   - `TELEGRAM_WEBHOOK_SECRET` - произвольная длинная строка для защиты webhook.
4. Дайте PHP право записи в папку `storage`.
5. Откройте `/index.php`, введите `SUPPORT_ADMIN_TOKEN`.

Важно: токен бота не храните в Git. Если токен был опубликован в переписке или логах, перевыпустите его через BotFather.

