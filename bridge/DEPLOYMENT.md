# How to Run the WhatsApp Bridge on Your Website / Server

## Why did you see `cURL error 7: Failed to connect to localhost`?
When your WordPress website is hosted online (e.g. on `mywebsite.com`) and tries to connect to `http://localhost:3000`, it looks inside **its own server**, not your personal computer! 

To make it work with your live website, choose one of the solutions below:

---

## Method 1: Instant Quick Tunnel (Fastest for testing)
If you are already running the bridge on your computer with `npm start`:

1. Open a second terminal window and run:
   ```bash
   cd /Users/michael/Desktop/AutoPublish/bridge
   npm run tunnel
   ```
2. It will output a public HTTPS URL like:
   ```text
   your url is: https://sweet-lion-42.loca.lt
   ```
3. Copy that URL and paste it into WordPress (**WhatsApp > Settings > Bridge / Gateway URL**).
4. Click **Save Settings** and test!

---

## Method 2: Run directly on your Website Server (VPS / Ubuntu / Debian)
If your website runs on a VPS (DigitalOcean, Hetzner, AWS, Linode, Ubuntu):

1. **Upload the `/bridge` folder** to your server (e.g. `/var/www/whatsapp-bridge`):
   ```bash
   scp -r /Users/michael/Desktop/AutoPublish/bridge user@your-server-ip:/var/www/whatsapp-bridge
   ```
2. **SSH into your server and install PM2** (Node process manager):
   ```bash
   ssh user@your-server-ip
   cd /var/www/whatsapp-bridge
   npm install
   sudo npm install -g pm2
   pm2 start server.js --name "whatsapp-bridge"
   pm2 startup
   pm2 save
   ```
3. **Open browser to link WhatsApp**:
   Visit `http://your-server-ip:3000` in your web browser and scan the QR code!
4. **Configure WordPress**:
   * If WordPress is on the **same server**: Set Bridge URL to `http://127.0.0.1:3000`.
   * If WordPress is on a **different server**: Set Bridge URL to `http://your-server-ip:3000` (or set up an Nginx reverse proxy with SSL `https://wa.yourwebsite.com`).

---

## Method 3: Run on cPanel Hosting ("Setup Node.js App")
If your website is hosted on cPanel:

1. Upload the files inside `/bridge` to your cPanel File Manager (e.g., in a directory called `wa-bridge`).
2. In cPanel, search for **Setup Node.js App** (Passenger).
3. Click **Create Application**:
   * **Node.js version**: 18 or 20
   * **Application root**: `wa-bridge`
   * **Application URL**: `wa-bridge` (or a subdomain like `wa.yourwebsite.com`)
   * **Application startup file**: `server.js`
4. Click **Create**, then click **Run NPM Install**.
5. Click **Restart**.
6. Open `https://yourwebsite.com/wa-bridge` to scan the QR code!
7. In WordPress plugin settings, set Bridge URL to `https://yourwebsite.com/wa-bridge`.

---

## Method 4: Deploy Free 24/7 on Cloud (Render.com)
If you don't have VPS access and want it running 24/7 with a permanent HTTPS address:

1. Create a free account at [Render.com](https://render.com).
2. Click **New + > Web Service**.
3. Connect your repository or upload the `/bridge` code.
   * **Build Command**: `npm install`
   * **Start Command**: `node server.js`
4. Render will provide a free permanent URL like `https://my-wa-bridge.onrender.com`.
5. Open that URL to scan the QR code and link your WhatsApp.
6. Enter `https://my-wa-bridge.onrender.com` into your WordPress plugin settings!
