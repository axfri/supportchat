# Support Chat

Автономный веб-чат поддержки для сайта и Telegram.

## Что уже есть

- Панель оператора: `public/index.php`
- Клиентская страница поддержки: `public/support.php`
- Автообновление сообщений в фоне
- Метки источника: `Сайт` и `Telegram`
- Статусы диалогов: `Новый`, `Открыт`, `Закрыт`
- Поиск и фильтры по каналу/статусу
- Закрытие и повторное открытие диалогов
- Прием Telegram-сообщений через webhook или polling
- Ответы оператора обратно в Telegram
- Защита от повторной записи одного Telegram-сообщения
- Ограничение частоты сообщений с сайта
- Локальный Node.js-сервер для разработки без PHP
- PHP + SQLite версия для установки на сервер

## Локальный запуск

Требуется Node.js 18+.

```bash
npm start
```

После запуска:

- панель поддержки: `http://localhost:8080/index.php`
- страница клиента: `http://localhost:8080/support.php`
- ключ доступа по умолчанию: `admin`

Если в `.env` указан `SUPPORT_ADMIN_TOKEN`, используйте его вместо `admin`.

## Локальный запуск с Telegram

1. Создайте `.env`.
2. Укажите `TELEGRAM_BOT_TOKEN`.
3. Запустите:

```bash
npm run start:telegram
```

4. Напишите сообщение боту в Telegram.
5. Откройте панель поддержки: появится диалог с меткой `Telegram`.

Важно: если у бота уже установлен webhook на сервер, polling может не получать сообщения. На время локальной проверки webhook нужно удалить через BotFather/API или использовать тестовый webhook-запрос ниже.

## Проверка без реального Telegram

```powershell
Invoke-RestMethod -Method Post http://localhost:8080/telegram-webhook.php `
  -ContentType "application/json" `
  -Body '{"message":{"message_id":1,"text":"Привет из Telegram","chat":{"id":12345,"first_name":"Test","username":"test_user"}}}'
```

После этого в панели появится диалог с меткой `Telegram`.

## Проверка сайта локально

1. Откройте `http://localhost:8080/support.php`.
2. Напишите сообщение от клиента.
3. Откройте `http://localhost:8080/index.php`.
4. Введите ключ доступа.
5. Выберите диалог с меткой `Сайт`.
6. Ответьте из панели.
7. Ответ появится на клиентской странице автоматически.

## Команды

```bash
npm start
npm run start:telegram
```

Изменить порт:

```powershell
$env:PORT=8090; npm start
```

Локальные данные хранятся в `storage/local-data.json` и не попадают в Git.

## Серверная установка

1. Скопируйте проект на отдельный сайт или поддомен.
2. Создайте `.env` из `.env.example`.
3. Заполните:
   - `APP_URL`
   - `SQLITE_PATH`
   - `SUPPORT_ADMIN_TOKEN`
   - `TELEGRAM_BOT_TOKEN`
   - `TELEGRAM_WEBHOOK_SECRET`
4. Дайте PHP право записи в папку `storage`.
5. Откройте `/index.php` и войдите по `SUPPORT_ADMIN_TOKEN`.

## Telegram webhook на сервере

```bash
curl "https://api.telegram.org/bot$TELEGRAM_BOT_TOKEN/setWebhook" \
  -d "url=$APP_URL/telegram-webhook.php?secret=$TELEGRAM_WEBHOOK_SECRET"
```

Можно также передавать секрет через заголовок `X-Telegram-Bot-Api-Secret-Token`, если webhook настраивается вручную.

## Telegram polling на сервере

Если webhook недоступен:

```bash
php bin/telegram-poll.php
```

## Безопасность

- Не храните `.env` в Git.
- Не храните реальный Telegram token в `.env.example`.
- Если token был отправлен в чат или лог, перевыпустите его через BotFather.
- Панель поддержки закрыта `SUPPORT_ADMIN_TOKEN`.
- Telegram webhook защищается `TELEGRAM_WEBHOOK_SECRET`.
