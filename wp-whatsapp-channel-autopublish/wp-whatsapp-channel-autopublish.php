<?php
/**
 * Plugin Name: WhatsApp Channel & Cloud API AutoPublish
 * Plugin URI:  https://github.com/autopublish/wp-whatsapp-channel-autopublish
 * Description: Automatically broadcast new WordPress posts to a WhatsApp Channel or via Meta WhatsApp Cloud API with featured images, custom formatting, and one-click manual broadcast.
 * Version:     1.0.0
 * Author:      AutoPublish Team
 * Author URI:  https://github.com/autopublish
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wp-whatsapp-channel-autopublish
 * Domain Path: /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPWCA_VERSION', '1.0.0' );
define( 'WPWCA_PLUGIN_FILE', __FILE__ );
define( 'WPWCA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPWCA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPWCA_SETTINGS_KEY', 'wpwca_settings' );
define( 'WPWCA_LOGS_KEY', 'wpwca_broadcast_logs' );

// Load classes.
require_once WPWCA_PLUGIN_DIR . 'includes/class-wpwca-activator.php';
require_once WPWCA_PLUGIN_DIR . 'includes/class-wpwca-logger.php';
require_once WPWCA_PLUGIN_DIR . 'includes/class-wpwca-meta-api.php';
require_once WPWCA_PLUGIN_DIR . 'includes/class-wpwca-channel-api.php';
require_once WPWCA_PLUGIN_DIR . 'includes/class-wpwca-broadcaster.php';
require_once WPWCA_PLUGIN_DIR . 'includes/class-wpwca-admin.php';

// Activation and deactivation hooks.
register_activation_hook( __FILE__, array( 'WPWCA_Activator', 'activate' ) );

// Initialize plugin components.
function wpwca_init_plugin() {
	$logger      = new WPWCA_Logger();
	$meta_api    = new WPWCA_Meta_API( $logger );
	$channel_api = new WPWCA_Channel_API( $logger );
	$broadcaster = new WPWCA_Broadcaster( $meta_api, $channel_api, $logger );
	$admin       = new WPWCA_Admin( $broadcaster, $logger );

	$broadcaster->init_hooks();
	$admin->init_hooks();
}
add_action( 'plugins_loaded', 'wpwca_init_plugin' );
