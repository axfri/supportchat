const http = require('http');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const rootDir = __dirname;
const publicDir = path.join(rootDir, 'public');
const storageDir = path.join(rootDir, 'storage');
const dataFile = path.join(storageDir, 'local-data.json');
const port = Number(process.env.PORT || 8080);

const env = loadEnv();
const adminToken = env.SUPPORT_ADMIN_TOKEN || 'admin';
const telegramToken = env.TELEGRAM_BOT_TOKEN || '';

fs.mkdirSync(storageDir, { recursive: true });

function loadEnv() {
  const result = {};
  const file = path.join(rootDir, '.env');
  if (!fs.existsSync(file)) return result;

  for (const rawLine of fs.readFileSync(file, 'utf8').split(/\r?\n/)) {
    const line = rawLine.trim();
    if (!line || line.startsWith('#') || !line.includes('=')) continue;
    const index = line.indexOf('=');
    const key = line.slice(0, index).trim();
    const value = line.slice(index + 1).trim().replace(/^["']|["']$/g, '');
    result[key] = value;
  }
  return result;
}

function readData() {
  if (!fs.existsSync(dataFile)) {
    return { nextConversationId: 1, nextMessageId: 1, conversations: [], messages: [] };
  }
  return JSON.parse(fs.readFileSync(dataFile, 'utf8'));
}

function writeData(data) {
  fs.writeFileSync(dataFile, JSON.stringify(data, null, 2), 'utf8');
}

function now() {
  return new Date().toISOString().slice(0, 19).replace('T', ' ');
}

function json(res, status, payload) {
  const body = JSON.stringify(payload);
  res.writeHead(status, {
    'Content-Type': 'application/json; charset=utf-8',
    'Content-Length': Buffer.byteLength(body),
  });
  res.end(body);
}

function text(res, status, body, type = 'text/plain; charset=utf-8') {
  res.writeHead(status, { 'Content-Type': type });
  res.end(body);
}

function readBody(req) {
  return new Promise((resolve) => {
    let body = '';
    req.on('data', (chunk) => {
      body += chunk;
      if (body.length > 1024 * 1024) req.destroy();
    });
    req.on('end', () => {
      try {
        resolve(body ? JSON.parse(body) : {});
      } catch {
        resolve({});
      }
    });
  });
}

function getCookie(req, name) {
  const cookie = req.headers.cookie || '';
  for (const part of cookie.split(';')) {
    const [key, ...value] = part.trim().split('=');
    if (key === name) return decodeURIComponent(value.join('='));
  }
  return '';
}

function requireAdmin(req, res) {
  const header = req.headers.authorization || '';
  const token = header.toLowerCase().startsWith('bearer ') ? header.slice(7).trim() : '';
  if (token !== adminToken) {
    json(res, 401, { ok: false, error: 'Unauthorized' });
    return false;
  }
  return true;
}

function getWebSession(req, res) {
  let sid = getCookie(req, 'support_chat_sid');
  if (!sid) {
    sid = crypto.randomBytes(18).toString('hex');
    res.setHeader('Set-Cookie', `support_chat_sid=${encodeURIComponent(sid)}; Path=/; SameSite=Lax`);
  }
  return sid;
}

function findOrCreateWebConversation(data, sid) {
  let conversation = data.conversations.find((item) => item.channel === 'web' && item.external_id === sid);
  if (conversation) return conversation;

  conversation = {
    id: data.nextConversationId++,
    channel: 'web',
    external_id: sid,
    visitor_name: 'Посетитель сайта',
    visitor_handle: '',
    status: 'open',
    unread_support: 0,
    unread_visitor: 0,
    created_at: now(),
    updated_at: now(),
  };
  data.conversations.push(conversation);
  return conversation;
}

function findOrCreateTelegramConversation(data, chatId, name, handle) {
  let conversation = data.conversations.find((item) => item.channel === 'telegram' && item.external_id === chatId);
  if (conversation) {
    conversation.visitor_name = name;
    conversation.visitor_handle = handle;
    conversation.updated_at = now();
    return conversation;
  }

  conversation = {
    id: data.nextConversationId++,
    channel: 'telegram',
    external_id: chatId,
    visitor_name: name || 'Telegram user',
    visitor_handle: handle || '',
    status: 'open',
    unread_support: 0,
    unread_visitor: 0,
    created_at: now(),
    updated_at: now(),
  };
  data.conversations.push(conversation);
  return conversation;
}

function addMessage(data, conversation, sender, body, telegramMessageId = '') {
  const message = String(body || '').trim();
  if (!message) throw new Error('Message is empty');
  if (Buffer.byteLength(message, 'utf8') > 12000) throw new Error('Message is too long');

  const item = {
    id: data.nextMessageId++,
    conversation_id: conversation.id,
    sender,
    body: message,
    telegram_message_id: telegramMessageId,
    created_at: now(),
  };
  data.messages.push(item);

  if (sender === 'visitor') conversation.unread_support += 1;
  if (sender === 'support') conversation.unread_visitor += 1;
  conversation.updated_at = now();

  return item;
}

async function sendTelegram(chatId, body) {
  if (!telegramToken) return;
  const endpoint = `https://api.telegram.org/bot${telegramToken}/sendMessage`;
  try {
    await fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        chat_id: chatId,
        text: body,
        disable_web_page_preview: 'true',
      }),
    });
  } catch {
    // Local server keeps working even when Telegram is unavailable.
  }
}

async function handleApi(req, res, url) {
  const data = readData();

  if (url.pathname === '/api/conversations.php') {
    if (!requireAdmin(req, res)) return;

    const conversations = data.conversations
      .map((conversation) => {
        const last = data.messages
          .filter((message) => message.conversation_id === conversation.id)
          .sort((a, b) => b.id - a.id)[0];
        return {
          ...conversation,
          last_message: last ? last.body : '',
          last_sender: last ? last.sender : '',
          last_message_at: last ? last.created_at : '',
        };
      })
      .sort((a, b) => b.updated_at.localeCompare(a.updated_at) || b.id - a.id);

    json(res, 200, { ok: true, conversations });
    return;
  }

  if (url.pathname === '/api/messages.php') {
    const isAdmin = url.searchParams.get('admin') === '1';
    if (isAdmin && !requireAdmin(req, res)) return;

    if (req.method === 'GET') {
      let conversation;
      if (isAdmin) {
        const id = Number(url.searchParams.get('conversation_id') || 0);
        conversation = data.conversations.find((item) => item.id === id);
      } else {
        conversation = findOrCreateWebConversation(data, getWebSession(req, res));
        writeData(data);
      }

      if (!conversation) {
        json(res, 422, { ok: false, error: 'Conversation is required' });
        return;
      }

      if (isAdmin) conversation.unread_support = 0;
      else conversation.unread_visitor = 0;
      writeData(data);

      const messages = data.messages
        .filter((message) => message.conversation_id === conversation.id)
        .sort((a, b) => a.id - b.id);

      json(res, 200, { ok: true, conversation_id: conversation.id, messages });
      return;
    }

    if (req.method === 'POST') {
      const payload = await readBody(req);
      const body = String(payload.body || '').trim();
      let conversation;

      if (isAdmin) {
        const id = Number(payload.conversation_id || 0);
        conversation = data.conversations.find((item) => item.id === id);
      } else {
        conversation = findOrCreateWebConversation(data, getWebSession(req, res));
      }

      if (!conversation) {
        json(res, 422, { ok: false, error: 'Conversation is required' });
        return;
      }

      try {
        const message = addMessage(data, conversation, isAdmin ? 'support' : 'visitor', body);
        writeData(data);

        if (isAdmin && conversation.channel === 'telegram') {
          await sendTelegram(conversation.external_id, body);
        }

        json(res, 200, { ok: true, message_id: message.id, conversation_id: conversation.id });
      } catch (error) {
        json(res, 422, { ok: false, error: error.message });
      }
      return;
    }
  }

  if (url.pathname === '/telegram-webhook.php' && req.method === 'POST') {
    const update = await readBody(req);
    const message = update.message || {};
    const chat = message.chat || {};
    if (!chat.id) {
      json(res, 200, { ok: true });
      return;
    }

    const name = `${chat.first_name || ''} ${chat.last_name || ''}`.trim() || 'Telegram user';
    const handle = chat.username ? `@${chat.username}` : '';
    const conversation = findOrCreateTelegramConversation(data, String(chat.id), name, handle);
    addMessage(data, conversation, 'visitor', message.text || '[не текстовое сообщение]', String(message.message_id || ''));
    writeData(data);
    json(res, 200, { ok: true });
    return;
  }

  json(res, 404, { ok: false, error: 'Not found' });
}

function serveStatic(req, res, url) {
  let pathname = decodeURIComponent(url.pathname);
  if (pathname === '/') pathname = '/index.php';

  const filePath = path.normalize(path.join(publicDir, pathname));
  if (!filePath.startsWith(publicDir)) {
    text(res, 403, 'Forbidden');
    return;
  }

  if (!fs.existsSync(filePath) || !fs.statSync(filePath).isFile()) {
    text(res, 404, 'Not found');
    return;
  }

  const ext = path.extname(filePath).toLowerCase();
  const types = {
    '.php': 'text/html; charset=utf-8',
    '.html': 'text/html; charset=utf-8',
    '.css': 'text/css; charset=utf-8',
    '.js': 'application/javascript; charset=utf-8',
    '.json': 'application/json; charset=utf-8',
    '.svg': 'image/svg+xml',
    '.png': 'image/png',
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
  };

  text(res, 200, fs.readFileSync(filePath), types[ext] || 'application/octet-stream');
}

const server = http.createServer((req, res) => {
  const url = new URL(req.url, `http://${req.headers.host || `localhost:${port}`}`);

  if (url.pathname.startsWith('/api/') || url.pathname === '/telegram-webhook.php') {
    handleApi(req, res, url).catch((error) => {
      json(res, 500, { ok: false, error: error.message });
    });
    return;
  }

  serveStatic(req, res, url);
});

server.listen(port, () => {
  console.log(`Support chat local server: http://localhost:${port}`);
  console.log(`Admin panel: http://localhost:${port}/index.php`);
  console.log(`Client page: http://localhost:${port}/support.php`);
  console.log(`Admin token: ${adminToken}`);
});
