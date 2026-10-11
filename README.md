# WordPress to WhatsApp Channel AutoPublish (Self-Hosted)

A 100% self-hosted, private solution to automatically broadcast new WordPress blog posts directly to your **WhatsApp Channel** the moment they are published.

---

## 💡 Why Self-Hosted?

* **No Monthly Fees**: Avoid paid third-party platforms (like Whapi, UltraMsg, etc.).
* **Full Privacy**: Your data and WhatsApp sessions remain exclusively on your own machine/server.
* **Direct Channel Support**: WhatsApp Channels (`@newsletter`) are fully supported through our included companion bridge.
* **No Meta Business Verification Required**: Simply link via a QR code scan.

---

## 📁 Repository Contents

* **[`wp-whatsapp-channel-autopublish/`](file:///Users/michael/Desktop/AutoPublish/wp-whatsapp-channel-autopublish)**: The WordPress plugin source code.
* **[`wp-whatsapp-channel-autopublish.zip`](file:///Users/michael/Desktop/AutoPublish/wp-whatsapp-channel-autopublish.zip)**: The ready-to-upload ZIP package for WordPress.
* **[`bridge/`](file:///Users/michael/Desktop/AutoPublish/bridge)**: The self-hosted Node.js WhatsApp Bridge with built-in web dashboard, QR code scanner, and Channel JID resolver.
* **[`build-plugin-zip.sh`](file:///Users/michael/Desktop/AutoPublish/build-plugin-zip.sh)**: Packaging script to recompile the plugin ZIP.

---

## 🚀 Setup Walkthrough (3 Quick Steps)

### Step 1: Start the Self-Hosted Bridge

```bash
cd bridge
npm install
npm start
```

1. Open **`http://localhost:3000`** in your browser.
2. In WhatsApp on your phone, go to **Settings (or ⋮) > Linked Devices > Link a Device**.
3. Scan the QR code shown on the webpage.
4. Your browser will immediately update to **● Connected**.
5. On the same page under **Channel JID Resolver**, paste your channel's invite link (e.g., `https://whatsapp.com/channel/0029Va...`) and copy the generated Channel JID (`120363xxxxxxxxxx@newsletter`).

*(Note: Docker users can run `docker compose up -d` inside `/bridge`)*

---

### Step 2: Install the WordPress Plugin

1. In WordPress Admin, go to **Plugins > Add New > Upload Plugin**.
2. Upload [`wp-whatsapp-channel-autopublish.zip`](file:///Users/michael/Desktop/AutoPublish/wp-whatsapp-channel-autopublish.zip) and click **Activate**.
3. A new **WhatsApp** menu item will appear in your left WordPress admin sidebar.

---

### Step 3: Configure Settings & Test

1. Navigate to **WhatsApp > Settings** in your WordPress admin.
2. The default provider is already set to **Self-Hosted Node.js Bridge**.
3. Verify the Bridge URL is `http://localhost:3000` (or your VPS address).
4. Paste your **Channel ID / JID** (from Step 1).
5. Click **Save Settings**, then click **Send Test Broadcast** in the right column to verify!

---

## ✍️ Publishing Workflow

* **Automatic on Publish**: When authors publish a new post, the bridge automatically broadcasts the post title, excerpt, URL, and featured image to your WhatsApp Channel.
* **Block Editor Integration**: When writing a post, open the **Post Settings (⚙️) tab in the right sidebar** to view the **WhatsApp Broadcast** panel:
  * Toggle automatic broadcast on/off for that post.
  * Check delivery status.
  * Click **Broadcast to WhatsApp Now** for manual broadcasting at any time.
