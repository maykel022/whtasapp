<?php
/**
 * Fired during plugin activation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPWCA_Activator {

	/**
	 * Default plugin options.
	 */
	public static function get_default_options() {
		return array(
			// Provider mode: 'channel_gateway' (Whapi/Evolution/Bridge for WhatsApp Channels) or 'meta_cloud' (Meta WhatsApp Cloud API) or 'custom_webhook'
			'provider_mode'            => 'channel_gateway',

			// WhatsApp Channel Gateway settings (Self-hosted Bridge as default)
			'channel_gateway_type'     => 'bridge', // 'bridge' (default), 'whapi', 'evolution'
			'channel_gateway_token'    => '',
			'channel_gateway_jid'      => '', // e.g. 120363xxxxxxxxxx@newsletter
			'channel_gateway_url'      => 'http://localhost:3000', // Self-hosted bridge default

			// Meta WhatsApp Cloud API settings
			'meta_access_token'        => '',
			'meta_phone_number_id'     => '',
			'meta_recipient_numbers'   => '', // Comma-separated list of E.164 phone numbers (e.g., +1234567890)
			'meta_message_mode'        => 'text', // 'text' or 'template'
			'meta_template_name'       => '',
			'meta_template_language'   => 'en_US',

			// Content & Broadcast settings
			'auto_broadcast_on_publish' => 'yes',
			'post_types'               => array( 'post' ),
			'include_featured_image'   => 'yes',
			'message_template'         => "🚀 *New Post:* {title}\n\n{excerpt}\n\n📖 Read full article:\n{url}",

			// Logging
			'enable_logging'           => 'yes',
		);
	}

	/**
	 * Run on activation.
	 */
	public static function activate() {
		$existing = get_option( WPWCA_SETTINGS_KEY );
		if ( false === $existing ) {
			add_option( WPWCA_SETTINGS_KEY, self::get_default_options() );
		} else {
			// Merge new defaults into existing options to preserve existing configuration.
			$defaults = self::get_default_options();
			$merged   = wp_parse_args( $existing, $defaults );
			update_option( WPWCA_SETTINGS_KEY, $merged );
		}
	}
}
