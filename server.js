/**
 * Self-Hosted WhatsApp Channel Bridge
 * 
 * A private, zero-cost bridge that links your WhatsApp account directly
 * and broadcasts WordPress blog posts to WhatsApp Channels (@newsletter).
 */

require('dotenv').config();
const express = require('express');
const cors = require('cors');
const path = require('path');
const fs = require('fs');
const pino = require('pino');
const qrcodeTerminal = require('qrcode-terminal');
const QRCode = require('qrcode');

const {
    default: makeWASocket,
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion
} = require('@whiskeysockets/baileys');

const app = express();
app.use(cors());
app.use(express.json());

const PORT = process.env.PORT || 3000;
const API_SECRET = process.env.API_SECRET || '';
const AUTH_DIR = path.join(__dirname, 'auth_info_baileys');

let sock = null;
let isConnected = false;
let currentQr = null;
let qrDataUrl = null;
let connectedUser = null;

// Logger
const logger = pino({ level: 'silent' });

/**
 * Initialize Baileys WhatsApp Socket
 */
async function initWhatsApp() {
    if (!fs.existsSync(AUTH_DIR)) {
        fs.mkdirSync(AUTH_DIR, { recursive: true });
    }

    const { state, saveCreds } = await useMultiFileAuthState(AUTH_DIR);
    const { version, isLatest } = await fetchLatestBaileysVersion();
    console.log(`[Bridge] Using Baileys version: ${version.join('.')}, isLatest: ${isLatest}`);

    sock = makeWASocket({
        version,
        logger,
        auth: state,
        printQRInTerminal: false,
        browser: ['AutoPublish Self-Hosted', 'Chrome', '1.0.0']
    });

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            currentQr = qr;
            try {
                qrDataUrl = await QRCode.toDataURL(qr, { margin: 2, scale: 7 });
            } catch (err) {
                console.error('[Bridge] Error creating QR DataURL:', err);
            }
            console.log('\n[Bridge] 📱 Scan this QR code in WhatsApp (Linked Devices):');
            qrcodeTerminal.generate(qr, { small: true });
        }

        if (connection === 'close') {
            isConnected = false;
            currentQr = null;
            qrDataUrl = null;
            connectedUser = null;

            const statusCode = lastDisconnect?.error?.output?.statusCode;
            const shouldReconnect = statusCode !== DisconnectReason.loggedOut;
            console.log(`[Bridge] Connection closed (code: ${statusCode}). Reconnecting: ${shouldReconnect}`);

            if (shouldReconnect) {
                setTimeout(initWhatsApp, 4000);
            } else {
                console.log('[Bridge] Logged out. Clearing credentials...');
                if (fs.existsSync(AUTH_DIR)) {
                    fs.rmSync(AUTH_DIR, { recursive: true, force: true });
                }
                setTimeout(initWhatsApp, 2000);
            }
        } else if (connection === 'open') {
            isConnected = true;
            currentQr = null;
            qrDataUrl = null;
            connectedUser = sock?.user || null;
            console.log(`✅ [Bridge] Successfully connected to WhatsApp as ${connectedUser?.id || 'User'}!`);
        }
    });
}

/**
 * Authentication Middleware for POST /broadcast
 */
function authMiddleware(req, res, next) {
    if (!API_SECRET) {
        return next(); // No secret configured; allow open access
    }

    const authHeader = req.headers.authorization;
    if (!authHeader) {
        return res.status(401).json({ success: false, error: 'Authorization header required' });
    }

    const token = authHeader.replace(/^Bearer\s+/i, '').trim();
    if (token !== API_SECRET) {
        return res.status(403).json({ success: false, error: 'Invalid API Secret' });
    }

    next();
}

/**
 * Web UI: Interactive Dashboard
 */
app.get('/', (req, res) => {
    res.send(`
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Channel Self-Hosted Bridge</title>
    <style>
        :root {
            --wa-green: #25D366;
            --wa-teal: #075E54;
            --wa-dark: #128C7E;
            --bg-color: #f0f2f5;
            --card-bg: #ffffff;
            --text-main: #111b21;
            --text-sub: #667781;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg-color);
            color: var(--text-main);
            margin: 0;
            padding: 30px 20px;
            display: flex;
            justify-content: center;
        }
        .container {
            max-width: 680px;
            width: 100%;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
        }
        .header h1 {
            color: var(--wa-teal);
            margin: 0 0 8px 0;
            font-size: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .header p {
            color: var(--text-sub);
            margin: 0;
            font-size: 15px;
        }
        .card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(11, 20, 26, 0.08);
        }
        .card h2 {
            margin-top: 0;
            font-size: 18px;
            color: var(--wa-teal);
            border-bottom: 1px solid #e9edef;
            padding-bottom: 10px;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 14.5px;
        }
        .status-connected {
            background: #d4edda;
            color: #155724;
        }
        .status-disconnected {
            background: #fff3cd;
            color: #856404;
        }
        .qr-wrapper {
            text-align: center;
            padding: 20px 0;
        }
        .qr-img {
            max-width: 260px;
            width: 100%;
            height: auto;
            border-radius: 12px;
            border: 2px solid #e9edef;
            padding: 10px;
            background: #fff;
        }
        .instructions {
            background: #f8f9fa;
            border-left: 4px solid var(--wa-green);
            padding: 14px 18px;
            border-radius: 4px;
            font-size: 14px;
            line-height: 1.6;
            margin-top: 15px;
        }
        .instructions ol {
            margin: 8px 0 0 18px;
            padding: 0;
        }
        .form-group {
            margin-bottom: 14px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13.5px;
            margin-bottom: 5px;
            color: var(--text-main);
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccd0d5;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .btn {
            background: var(--wa-green);
            color: #fff;
            border: none;
            padding: 10px 18px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.15s;
        }
        .btn:hover {
            background: var(--wa-dark);
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #bd2130;
        }
        .code-pill {
            background: #e9edef;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 13px;
        }
        .alert-box {
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 13.5px;
            margin-top: 12px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 WhatsApp Channel Bridge</h1>
            <p>Self-Hosted, Private Broadcast Gateway for WordPress</p>
        </div>

        <!-- Connection Status Card -->
        <div class="card">
            <h2>WhatsApp Connection Status</h2>
            <div id="statusContainer" style="margin-bottom: 15px;">
                <span class="status-badge ${isConnected ? 'status-connected' : 'status-disconnected'}">
                    ${isConnected ? '● Connected' : '○ Waiting for Connection / QR Scan'}
                </span>
                ${connectedUser ? `<p style="margin: 8px 0 0 0; font-size: 14px; color: var(--text-sub);">Connected Account: <strong>${connectedUser.id ? connectedUser.id.split(':')[0] : 'WhatsApp User'}</strong></p>` : ''}
            </div>

            <div id="qrArea" class="qr-wrapper" style="${isConnected ? 'display:none;' : ''}">
                <p>Scan with WhatsApp on your phone under <strong>Linked Devices</strong>:</p>
                <img id="qrImage" class="qr-img" src="${qrDataUrl || ''}" alt="WhatsApp QR Code" style="${qrDataUrl ? '' : 'display:none;'}" />
                <p id="qrWaitText" style="${qrDataUrl ? 'display:none;' : ''}"><em>Generating pairing QR code...</em></p>
                
                <div class="instructions">
                    <strong>How to link:</strong>
                    <ol>
                        <li>Open WhatsApp on your smartphone.</li>
                        <li>Tap <strong>Settings</strong> (iOS) or <strong>Three Dots ⋮</strong> (Android) > <strong>Linked Devices</strong>.</li>
                        <li>Tap <strong>Link a Device</strong> and point your camera at this QR code.</li>
                    </ol>
                </div>
            </div>

            ${isConnected ? `
                <div style="margin-top: 15px;">
                    <button class="btn btn-danger" onclick="logoutWhatsApp()">Unlink / Logout Session</button>
                </div>
            ` : ''}
        </div>

        <!-- Channel JID Resolver Tool -->
        <div class="card">
            <h2>Channel JID Resolver</h2>
            <p style="font-size: 13.5px; color: var(--text-sub); margin-top: 0;">
                Need your WhatsApp Channel ID ending in <code class="code-pill">@newsletter</code>? Enter your channel's public invite link below to extract its unique JID:
            </p>
            <div class="form-group">
                <label for="inviteInput">WhatsApp Channel Invite Link or Code</label>
                <input type="text" id="inviteInput" placeholder="https://whatsapp.com/channel/0029Va... or code" />
            </div>
            <button class="btn" onclick="resolveChannel()">Find Channel JID</button>
            <div id="resolveResult" class="alert-box"></div>
        </div>

        <!-- Quick Test Broadcast -->
        <div class="card">
            <h2>Send Quick Test Broadcast</h2>
            <div class="form-group">
                <label for="testTarget">Channel JID</label>
                <input type="text" id="testTarget" placeholder="120363xxxxxxxxxx@newsletter" />
            </div>
            <div class="form-group">
                <label for="testMessage">Message</label>
                <textarea id="testMessage" rows="3">🧪 Hello from the Self-Hosted WhatsApp Bridge! Your integration is ready to broadcast.</textarea>
            </div>
            <button class="btn" onclick="sendTestBroadcast()">Send Test Message</button>
            <div id="testResult" class="alert-box"></div>
        </div>

        <!-- WordPress Configuration Help -->
        <div class="card">
            <h2>WordPress Plugin Configuration</h2>
            <p style="font-size: 14px; line-height: 1.6; color: var(--text-main);">
                In your WordPress Admin under <strong>WhatsApp > Settings</strong>:
            </p>
            <ul style="font-size: 13.5px; line-height: 1.8; color: var(--text-sub);">
                <li>Provider Mode: <strong>WhatsApp Channel Broadcast</strong></li>
                <li>Gateway Provider: <strong>Self-Hosted Node.js Bridge (Local / Free)</strong></li>
                <li>Gateway URL: <code>http://localhost:${PORT}</code> (or your server's domain/IP)</li>
                <li>Channel ID / JID: <code>&lt;your-channel-jid&gt;@newsletter</code></li>
                ${API_SECRET ? `<li>API Secret: <code>${API_SECRET}</code></li>` : '<li>API Secret: <em>None (open access on local port)</em></li>'}
            </ul>
        </div>
    </div>

    <script>
        // Auto-refresh status
        let lastConnected = ${isConnected};
        setInterval(async () => {
            try {
                const res = await fetch('/status');
                const data = await res.json();
                if (data.connected !== lastConnected) {
                    location.reload();
                } else if (!data.connected && data.qrDataUrl) {
                    const img = document.getElementById('qrImage');
                    if (img) {
                        img.src = data.qrDataUrl;
                        img.style.display = 'inline-block';
                        const waitText = document.getElementById('qrWaitText');
                        if (waitText) waitText.style.display = 'none';
                    }
                }
            } catch (e) {}
        }, 2500);

        async function resolveChannel() {
            const input = document.getElementById('inviteInput').value.trim();
            const resBox = document.getElementById('resolveResult');
            if (!input) {
                alert('Please enter an invite link or code.');
                return;
            }

            resBox.style.display = 'block';
            resBox.style.background = '#e7f7ee';
            resBox.style.color = '#0f6848';
            resBox.innerHTML = 'Resolving channel metadata...';

            try {
                const res = await fetch('/resolve-channel?link=' + encodeURIComponent(input));
                const data = await res.json();
                if (data.success && data.channel) {
                    resBox.innerHTML = '<strong>Found Channel:</strong><br>' +
                        'Name: <b>' + data.channel.name + '</b><br>' +
                        'Channel JID: <code class="code-pill">' + data.channel.id + '</code><br>' +
                        '<small>Copy this Channel JID into your WordPress settings!</small>';
                    document.getElementById('testTarget').value = data.channel.id;
                } else {
                    resBox.style.background = '#fdf2f2';
                    resBox.style.color = '#d63638';
                    resBox.innerText = data.error || 'Could not resolve channel.';
                }
            } catch (err) {
                resBox.style.background = '#fdf2f2';
                resBox.style.color = '#d63638';
                resBox.innerText = 'Network error: ' + err.message;
            }
        }

        async function sendTestBroadcast() {
            const to = document.getElementById('testTarget').value.trim();
            const text = document.getElementById('testMessage').value.trim();
            const resBox = document.getElementById('testResult');

            if (!to || !text) {
                alert('Please enter both Channel JID and Message.');
                return;
            }

            resBox.style.display = 'block';
            resBox.style.background = '#e7f7ee';
            resBox.style.color = '#0f6848';
            resBox.innerText = 'Sending broadcast...';

            try {
                const res = await fetch('/broadcast', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ to, text })
                });
                const data = await res.json();
                if (data.success) {
                    resBox.innerText = '✅ Broadcast successfully sent to ' + to;
                } else {
                    resBox.style.background = '#fdf2f2';
                    resBox.style.color = '#d63638';
                    resBox.innerText = '❌ Failed: ' + (data.error || 'Unknown error');
                }
            } catch (err) {
                resBox.style.background = '#fdf2f2';
                resBox.style.color = '#d63638';
                resBox.innerText = 'Network error: ' + err.message;
            }
        }

        async function logoutWhatsApp() {
            if (!confirm('Are you sure you want to unlink and logout this WhatsApp account?')) return;
            await fetch('/logout', { method: 'POST' });
            location.reload();
        }
    </script>
</body>
</html>
    `);
});

/**
 * Status endpoint
 */
app.get('/status', (req, res) => {
    res.json({
        connected: isConnected,
        hasQr: !!currentQr,
        qrDataUrl: qrDataUrl || null,
        user: connectedUser
    });
});

/**
 * Resolve Channel Invite Link to Newsletter JID
 */
app.get('/resolve-channel', async (req, res) => {
    const rawLink = req.query.link || '';
    if (!rawLink) {
        return res.status(400).json({ success: false, error: 'Missing link query parameter' });
    }

    if (!isConnected || !sock) {
        return res.status(503).json({ success: false, error: 'Bridge not connected to WhatsApp yet' });
    }

    // Extract code from link (e.g. https://whatsapp.com/channel/0029Va4x... -> 0029Va4x...)
    let code = rawLink.trim();
    const match = code.match(/channel\/([A-Za-z0-9_-]+)/);
    if (match && match[1]) {
        code = match[1];
    }

    try {
        const metadata = await sock.newsletterMetadata('invite', code);
        if (metadata && metadata.id) {
            return res.json({
                success: true,
                channel: {
                    id: metadata.id,
                    name: metadata.thread_metadata?.name?.text || metadata.name || 'Unnamed Channel',
                    subscribers: metadata.thread_metadata?.subscribers_count || 0
                }
            });
        } else {
            return res.status(404).json({ success: false, error: 'Channel metadata not found' });
        }
    } catch (err) {
        return res.status(500).json({ success: false, error: err.message || 'Failed to resolve channel' });
    }
});

/**
 * Broadcast Endpoint for WordPress AutoPublish
 */
app.post('/broadcast', authMiddleware, async (req, res) => {
    const { to, text, image, caption } = req.body;

    if (!isConnected || !sock) {
        return res.status(503).json({
            success: false,
            error: 'WhatsApp Bridge is disconnected. Please visit http://localhost:' + PORT + ' and scan the QR code.'
        });
    }

    if (!to) {
        return res.status(400).json({
            success: false,
            error: 'Missing required parameter "to" (WhatsApp Channel JID)'
        });
    }

    let targetJid = to.trim();
    if (!targetJid.includes('@')) {
        targetJid = `${targetJid}@newsletter`;
    }

    try {
        let sentMessage;

        if (image) {
            sentMessage = await sock.sendMessage(targetJid, {
                image: { url: image },
                caption: caption || text || ''
            });
        } else {
            sentMessage = await sock.sendMessage(targetJid, {
                text: text || ''
            });
        }

        console.log(`[Bridge] Broadcast sent to ${targetJid} (ID: ${sentMessage?.key?.id || 'ok'})`);
        return res.json({
            success: true,
            target: targetJid,
            messageId: sentMessage?.key?.id || null,
            timestamp: new Date().toISOString()
        });
    } catch (err) {
        console.error('[Bridge] Failed to send broadcast:', err);
        return res.status(500).json({
            success: false,
            error: err.message || 'Failed to deliver message to channel'
        });
    }
});

/**
 * Logout / Reset Session Endpoint
 */
app.post('/logout', async (req, res) => {
    try {
        if (sock) {
            await sock.logout();
        }
        if (fs.existsSync(AUTH_DIR)) {
            fs.rmSync(AUTH_DIR, { recursive: true, force: true });
        }
        isConnected = false;
        currentQr = null;
        qrDataUrl = null;
        connectedUser = null;
        setTimeout(initWhatsApp, 1500);
        return res.json({ success: true, message: 'Logged out successfully' });
    } catch (err) {
        return res.status(500).json({ success: false, error: err.message });
    }
});

// Start Server
app.listen(PORT, () => {
    console.log(`\n========================================================`);
    console.log(`🚀 [WhatsApp Bridge] Server running on http://localhost:${PORT}`);
    console.log(`👉 Open http://localhost:${PORT} in your browser to view status & QR code`);
    console.log(`========================================================\n`);
    initWhatsApp().catch((err) => console.error('Error starting WhatsApp socket:', err));
});
