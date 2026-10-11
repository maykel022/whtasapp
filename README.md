# WhatsApp Channel Self-Hosted Bridge

A lightweight, private Node.js service that connects your WhatsApp account directly to your WordPress website, allowing automatic broadcasting of blog posts to your **WhatsApp Channel** (`@newsletter`).

---

## 🚀 Features
* **Zero Monthly Cost**: 100% free, private alternative to third-party services.
* **Direct Channel Support**: Broadcasts directly to WhatsApp Channels (`@newsletter`).
* **Web Dashboard**: Displays live connection status and QR code pairing in your browser.
* **Channel JID Resolver**: Paste your channel's public invite link to automatically get its unique JID.
* **Docker Ready**: One-click deployment on Render, Railway, or VPS.

---

## 🐳 Quick Deployment on Render.com

1. In Render, select **New + > Web Service**.
2. Connect this repository: `https://github.com/maykel022/whtasapp`.
3. Render automatically detects the root `Dockerfile` and builds it.
4. Render will provide your public URL: `https://your-service.onrender.com`.
5. Open that URL in your browser to scan the WhatsApp pairing QR code!
6. Enter `https://your-service.onrender.com` in your WordPress plugin settings.

---

## 💻 Local Quick Start

```bash
npm install
npm start
```
Visit `http://localhost:3000` to view the dashboard and scan the QR code.
