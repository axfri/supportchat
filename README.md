# supportchat

PHP support system with a web chat interface, operator panel and Telegram integration.

The project connects website visitors and Telegram users with support staff through a shared conversation workspace. It is designed as a lightweight self-hosted application for customer support workflows.

## Features

- web support widget and operator chat interface;
- conversations from website and Telegram channels;
- admin and manager roles;
- login, session-based authentication and staff management;
- conversation statuses: new, open and closed;
- unread counters and conversation filters;
- text messages and file attachments;
- Telegram webhook and long-polling modes;
- Telegram send/delete operations and delivery logs;
- optional translation workflow;
- user balance and balance history;
- SQLite persistence with automatic schema migrations;
- JSON APIs used by the web interface;
- UTF-8 JSON and HTML responses.

## Stack

- PHP 8.1+;
- PDO SQLite;
- PHP sessions and password hashing;
- vanilla JavaScript, HTML and CSS;
- Node.js 18+ for the local development server;
- Telegram Bot API;
- optional Google Translate or LibreTranslate integration.

There is no framework dependency. The PHP application uses small focused modules under `src/` and public entry points under `public/`.

## Runtime modes

### PHP web runtime

The production-style runtime uses PHP through Apache, Nginx with PHP-FPM or another PHP-capable web server.

Set the web document root to:

```text
public/
```

The PHP entry points load the shared code from `src/` and create/update the SQLite schema automatically on first access.

### Node.js local server

The repository also contains a standalone Node.js development server:

```bash
npm install
npm start
```

It listens on port `8080` by default. Telegram polling can be enabled with:

```bash
npm run start:telegram
```

The Node server stores local development data in `storage/local-data.json`. It is intended for local development and should not be exposed directly to the public internet without an additional security review.

## PHP local setup

Requirements:

- PHP 8.1 or newer;
- PDO SQLite extension;
- a web server capable of executing PHP;
- write access to `storage/`;
- Node.js 18+ only if the local server is needed.

Create the environment file.

Linux/macOS:

```bash
cp .env.example .env
```

Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Start a local PHP server from the repository root:

```bash
php -S 127.0.0.1:8081 -t public
```

Then open:

```text
http://127.0.0.1:8081/login.php
```

The PHP runtime creates the SQLite database at the path configured by `SQLITE_PATH`. The default is `storage/support.sqlite`.

## Configuration

The main variables are documented in [`.env.example`](.env.example):

```env
APP_URL=https://example.com
SQLITE_PATH=storage/support.sqlite
SUPPORT_ADMIN_TOKEN=replace-with-a-long-random-secret
TELEGRAM_BOT_TOKEN=replace-with-a-bot-token
TELEGRAM_WEBHOOK_SECRET=replace-with-a-random-webhook-secret
TELEGRAM_POLLING=0
```

Additional optional variables used by the PHP application include:

- `SUPPORT_ADMIN_LOGIN`;
- `SUPPORT_ADMIN_PASSWORD`;
- `SUPPORT_ADMIN_PASSWORD_HASH`;
- `GOOGLE_TRANSLATE_API_KEY`;
- `LIBRETRANSLATE_API_KEY`;
- `TRANSLATION_API_KEY`.

Do not commit `.env`, SQLite databases, uploaded files, logs, Telegram offsets or API keys.

## Telegram webhook

The webhook entry point is:

```text
/public/telegram-webhook.php
```

Configure Telegram to send updates to the public HTTPS URL and set the same value as `TELEGRAM_WEBHOOK_SECRET`. The endpoint validates Telegram's secret header before processing updates.

For local-only development, use polling instead:

```bash
npm run start:telegram
```

Do not run webhook and polling against the same bot at the same time.

## API areas

The PHP API entry points are located in `public/api/`:

- `admin_login.php` and `admin_logout.php` — staff authentication;
- `conversations.php` — conversation list and status operations;
- `messages.php` — read, send, delete and attachment operations;
- `unread.php` — unread counters;
- `staff.php` — staff management;
- `admin_users.php` — administrator user operations;
- `telegram_logs.php` — Telegram delivery logs;
- `balance.php` — balance and balance history;
- `download.php` — attachment downloads;
- `avatar.php` — visitor avatar handling.

The browser UI uses JSON responses with UTF-8 content types. Authentication requirements depend on whether the request is made for a visitor session or an authenticated support staff member.

## Storage and database

The PHP application uses SQLite and performs migrations in `src/database.php`. Main tables include:

- `conversations`;
- `messages`;
- `attachments`;
- `support_staff`;
- `telegram_logs`;
- `balance_history`.

SQLite is configured with foreign keys, WAL mode, a busy timeout and indexes for conversations, messages, attachments and Telegram logs.

The `storage/` directory must be writable by the PHP process. It is ignored by Git except for `storage/.gitkeep`.

## Security checklist

Before deployment:

1. Set a long random `SUPPORT_ADMIN_TOKEN`.
2. Set a unique `TELEGRAM_WEBHOOK_SECRET`.
3. Create staff accounts with strong passwords.
4. Serve the application over HTTPS.
5. Keep `storage/` outside the public document root where possible.
6. Ensure uploaded files cannot be executed as PHP.
7. Restrict database and log file permissions.
8. Configure a real process supervisor for the required runtime.
9. Review upload limits and allowed file types.
10. Disable or replace development fallbacks before production use.

The local Node server currently has development-oriented behavior, including a fallback admin token when no token is configured. Do not expose it publicly without changing that behavior and reviewing the deployment configuration.

## Current limitations

- no Docker or docker-compose configuration;
- no automated test suite;
- no CI/CD workflow;
- no generated OpenAPI/Swagger specification;
- SQLite schema migrations are embedded in application startup;
- translation providers are optional integrations;
- the Node.js server and PHP runtime use different local storage implementations.

These are known limitations of the current lightweight implementation.

## License

No license is currently declared. Add an explicit license before distributing the project as reusable software.

