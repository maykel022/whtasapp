<?php
/**
 * Logger class for tracking WhatsApp broadcast events.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPWCA_Logger {

	const MAX_LOGS = 50;

	/**
	 * Log a broadcast event.
	 *
	 * @param string $status    'success', 'error', 'info'
	 * @param string $title     Title or summary
	 * @param string $message   Detailed message or API response
	 * @param int    $post_id   Optional post ID
	 * @param string $provider  Provider used ('channel_gateway', 'meta_cloud', etc.)
	 */
	public function log( $status, $title, $message, $post_id = 0, $provider = '' ) {
		$options = get_option( WPWCA_SETTINGS_KEY, array() );
		if ( isset( $options['enable_logging'] ) && 'no' === $options['enable_logging'] ) {
			return;
		}

		$logs = get_option( WPWCA_LOGS_KEY, array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		$entry = array(
			'id'        => uniqid( 'log_' ),
			'time'      => current_time( 'mysql' ),
			'status'    => sanitize_key( $status ),
			'title'     => sanitize_text_field( $title ),
			'message'   => is_array( $message ) || is_object( $message ) ? wp_json_encode( $message ) : sanitize_text_field( (string) $message ),
			'post_id'   => absint( $post_id ),
			'provider'  => sanitize_text_field( $provider ),
		);

		array_unshift( $logs, $entry );

		if ( count( $logs ) > self::MAX_LOGS ) {
			$logs = array_slice( $logs, 0, self::MAX_LOGS );
		}

		update_option( WPWCA_LOGS_KEY, $logs, false );
	}

	/**
	 * Retrieve all log entries.
	 *
	 * @return array
	 */
	public function get_logs() {
		$logs = get_option( WPWCA_LOGS_KEY, array() );
		return is_array( $logs ) ? $logs : array();
	}

	/**
	 * Clear all logs.
	 */
	public function clear_logs() {
		update_option( WPWCA_LOGS_KEY, array(), false );
	}
}
