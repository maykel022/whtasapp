# Self-Hosted WhatsApp Channel Bridge

A private, 100% free Node.js service that connects your WhatsApp account directly to your WordPress website, allowing automatic broadcasting of new blog posts to your **WhatsApp Channel** (`@newsletter`).

---

## 🌟 Advantages of Self-Hosting
* **Zero Subscription Fees**: No recurring payments to Whapi, Twilio, or third-party gateways.
* **100% Privacy**: Your messages, media, and credentials never pass through any external service.
* **Direct Channel Support**: WhatsApp Channels (`@newsletter`) are fully supported.
* **Interactive Web Dashboard**: Scan QR codes directly in your browser, resolve channel JIDs from invite links, and test broadcasts with one click.
* **Persistent Sessions**: Log in once; authentication survives server restarts.

---

## 🚀 Quick Start (Node.js)

### 1. Install & Run
```bash
cd bridge
npm install
npm start
```

### 2. Connect Your WhatsApp Account
1. Open your browser at **`http://localhost:3000`** (or view the terminal).
2. Open WhatsApp on your phone:
   * **iOS**: Settings > Linked Devices > Link a Device.
   * **Android**: Tap the 3 dots ⋮ > Linked Devices > Link a Device.
3. Scan the QR code displayed on the screen.
4. The dashboard will automatically refresh and display **● Connected**.

### 3. Find Your Channel JID
On the dashboard at `http://localhost:3000`:
1. Scroll to the **Channel JID Resolver** card.
2. Paste your WhatsApp Channel invite link (e.g., `https://whatsapp.com/channel/0029Va...`).
3. Click **Find Channel JID**.
4. It will return your channel ID (e.g., `120363171744447809@newsletter`).

---

## 🐳 Running with Docker (Optional)

If you prefer running via Docker / Docker Compose on your server:

```bash
cd bridge
docker compose up -d
```
The bridge will start on port `3000` with automated container restarts and persistent session storage in `./auth_info_baileys`.

---

## ⚙️ WordPress Plugin Configuration

In WordPress Admin:
1. Go to **WhatsApp > Settings** (or Settings > WhatsApp AutoPublish).
2. Set **Delivery Method** to: `WhatsApp Channel Broadcast`.
3. Set **Gateway Provider** to: `Self-Hosted Node.js Bridge (Recommended - 100% Free & Private)`.
4. Set **Bridge URL** to: `http://localhost:3000` (or `http://your-server-ip:3000` if hosted on a VPS).
5. Enter your **Channel ID / JID** (from Step 3 above).
6. Click **Save Settings** and test via the **Send Test Broadcast** button.
