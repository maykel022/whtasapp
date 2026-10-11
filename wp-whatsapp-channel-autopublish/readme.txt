=== WhatsApp Channel & Cloud API AutoPublish ===
Contributors: autopublishteam
Tags: whatsapp, whatsapp channel, newsletter, autopublish, broadcast, social broadcast
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Automatically broadcast new WordPress blog posts to your WhatsApp Channel or via Meta WhatsApp Cloud API.

== Description ==

**WhatsApp Channel & Cloud API AutoPublish** automatically shares newly published WordPress posts directly to your official WhatsApp Channel (or to subscriber phone numbers via Meta Cloud API).

WhatsApp Channels operate under WhatsApp's "Updates" tab and allow creators, publications, and businesses to build public one-way subscriber broadcasts.

### Key Features
* **Public WhatsApp Channel Broadcast**: Supports publishing directly to public WhatsApp Channel JIDs (e.g. `120363xxxxxxxxxx@newsletter`) via Whapi.cloud, Evolution API, or the included companion self-hosted bridge.
* **Official Meta WhatsApp Cloud API Support**: Option to broadcast to subscriber phone numbers via official Meta Graph API.
* **Featured Images**: Automatically sends the post's featured image with the custom message as a caption.
* **Smart Anti-Duplicate Protection**: Never sends accidental duplicates when updating or revising already-published posts.
* **Custom Message Templates**: Full support for template tags: `{title}`, `{url}`, `{excerpt}`, `{author}`, `{categories}`, `{tags}`, and `{date}`.
* **Manual One-Click Broadcast**: Broadcast any post on demand from the WordPress editor sidebar with instant status feedback.
* **Activity & Audit Log**: Built-in logs of the last 50 broadcast attempts with detailed API responses and error codes.
* **Test Tool**: Integrated "Send Test Broadcast" button to verify your configuration in seconds.

== Installation ==

1. Upload the `wp-whatsapp-channel-autopublish` folder to the `/wp-content/plugins/` directory, or upload the `.zip` file via **Plugins > Add New > Upload Plugin**.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **Settings > WhatsApp AutoPublish**.
4. Configure your preferred mode (WhatsApp Channel Gateway or Meta Cloud API).
5. Click **Save Settings** and test using the **Send Test Broadcast** button.

== Frequently Asked Questions ==

= Does official Meta Cloud API support WhatsApp Channels directly? =
No. Meta's official WhatsApp Cloud API only supports sending messages to phone numbers (1-to-1 or template broadcasts). It does not have an API endpoint for public WhatsApp Channels (`@newsletter`). To publish directly to a public WhatsApp Channel, select the **WhatsApp Channel Broadcast** provider mode (using Whapi.cloud, Evolution API, or the free self-hosted bridge included).

= How do I find my WhatsApp Channel JID? =
In Whapi.cloud, run a `GET /newsletters` request with your API token. Your channel JID will look like `120363171744447809@newsletter`.
