<?php
/**
 * WhatsApp Channel Gateway Client (Whapi.cloud, Evolution API, Self-hosted Bridge).
 * 
 * WhatsApp Channels (newsletters) operate with a JID ending in @newsletter.
 * Official Meta Cloud API does NOT support posting to Channels directly.
 * This class handles posting to WhatsApp Channels via gateways.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPWCA_Channel_API {

	/**
	 * @var WPWCA_Logger
	 */
	protected $logger;

	public function __construct( WPWCA_Logger $logger ) {
		$logger = $logger;
		$this->logger = $logger;
	}

	/**
	 * Broadcast a message to a WhatsApp Channel.
	 *
	 * @param string      $message      Formatted text message.
	 * @param string|null $image_url    Optional featured image URL.
	 * @param int         $post_id      WordPress Post ID.
	 * @return array                    Array with 'success' (bool) and 'message' (string).
	 */
	public function broadcast( $message, $image_url = null, $post_id = 0 ) {
		$options = get_option( WPWCA_SETTINGS_KEY, array() );

		$gateway_type = isset( $options['channel_gateway_type'] ) ? $options['channel_gateway_type'] : 'whapi';
		$token        = isset( $options['channel_gateway_token'] ) ? trim( $options['channel_gateway_token'] ) : '';
		$channel_jid  = isset( $options['channel_gateway_jid'] ) ? trim( $options['channel_gateway_jid'] ) : '';
		$base_url     = isset( $options['channel_gateway_url'] ) ? rtrim( trim( $options['channel_gateway_url'] ), '/' ) : 'https://gate.whapi.cloud';

		if ( empty( $channel_jid ) ) {
			$err = __( 'Channel ID / JID is not configured in settings.', 'wp-whatsapp-channel-autopublish' );
			$this->logger->log( 'error', 'Channel Broadcast Failed', $err, $post_id, 'Channel Gateway' );
			return array( 'success' => false, 'message' => $err );
		}

		// Ensure JID has @newsletter suffix if missing and looks like an ID
		if ( false === strpos( $channel_jid, '@' ) ) {
			$channel_jid .= '@newsletter';
		}

		if ( 'bridge' === $gateway_type ) {
			return $this->send_via_bridge( $base_url, $token, $channel_jid, $message, $image_url, $post_id );
		} elseif ( 'evolution' === $gateway_type ) {
			return $this->send_via_evolution( $base_url, $token, $channel_jid, $message, $image_url, $post_id );
		} else {
			// Default to Whapi.Cloud
			return $this->send_via_whapi( $base_url, $token, $channel_jid, $message, $image_url, $post_id );
		}
	}

	/**
	 * Send via Whapi.cloud API
	 */
	protected function send_via_whapi( $base_url, $token, $channel_jid, $message, $image_url, $post_id ) {
		if ( empty( $token ) ) {
			$err = __( 'Whapi.cloud API token is not configured.', 'wp-whatsapp-channel-autopublish' );
			$this->logger->log( 'error', 'Whapi Broadcast Failed', $err, $post_id, 'Whapi.cloud' );
			return array( 'success' => false, 'message' => $err );
		}

		$headers = array(
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
		);

		// If image is present, use /messages/image endpoint
		if ( ! empty( $image_url ) ) {
			$endpoint = $base_url . '/messages/image';
			$payload  = array(
				'to'      => $channel_jid,
				'media'   => $image_url,
				'caption' => $message,
			);
		} else {
			$endpoint = $base_url . '/messages/text';
			$payload  = array(
				'to'   => $channel_jid,
				'body' => $message,
			);
		}

		$response = wp_remote_post( $endpoint, array(
			'headers'     => $headers,
			'body'        => wp_json_encode( $payload ),
			'timeout'     => 20,
			'sslverify'   => true,
			'data_format' => 'body',
		) );

		if ( is_wp_error( $response ) ) {
			$err = $response->get_error_message();
			$this->logger->log( 'error', 'Whapi Connection Error', $err, $post_id, 'Whapi.cloud' );
			return array( 'success' => false, 'message' => $err );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 ) {
			$msg = sprintf( __( 'Successfully broadcasted to channel %s (HTTP %d)', 'wp-whatsapp-channel-autopublish' ), $channel_jid, $code );
			$this->logger->log( 'success', 'Whapi Broadcast Success', $body, $post_id, 'Whapi.cloud' );
			return array( 'success' => true, 'message' => $msg );
		} else {
			$error_detail = isset( $body['message'] ) ? $body['message'] : ( isset( $body['error']['message'] ) ? $body['error']['message'] : wp_remote_retrieve_body( $response ) );
			$msg = sprintf( __( 'Whapi Error (HTTP %d): %s', 'wp-whatsapp-channel-autopublish' ), $code, $error_detail );
			$this->logger->log( 'error', 'Whapi Broadcast Failed', $msg, $post_id, 'Whapi.cloud' );
			return array( 'success' => false, 'message' => $msg );
		}
	}

	/**
	 * Send via Self-Hosted Bridge (Local Node.js Baileys Bridge)
	 */
	protected function send_via_bridge( $base_url, $token, $channel_jid, $message, $image_url, $post_id ) {
		$endpoint = $base_url . '/broadcast';
		$payload  = array(
			'to'      => $channel_jid,
			'text'    => $message,
			'image'   => $image_url,
			'caption' => $message,
			'post_id' => $post_id,
		);

		$headers = array(
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		);
		if ( ! empty( $token ) ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$response = wp_remote_post( $endpoint, array(
			'headers'     => $headers,
			'body'        => wp_json_encode( $payload ),
			'timeout'     => 15,
			'sslverify'   => false,
		) );

		if ( is_wp_error( $response ) ) {
			$err = $response->get_error_message();
			$this->logger->log( 'error', 'Bridge Connection Error', $err, $post_id, 'Self-Hosted Bridge' );
			return array( 'success' => false, 'message' => $err );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 && ( ! isset( $body['success'] ) || true === $body['success'] ) ) {
			$msg = sprintf( __( 'Bridge broadcasted to channel %s', 'wp-whatsapp-channel-autopublish' ), $channel_jid );
			$this->logger->log( 'success', 'Bridge Broadcast Success', $body, $post_id, 'Self-Hosted Bridge' );
			return array( 'success' => true, 'message' => $msg );
		} else {
			$error_detail = isset( $body['error'] ) ? $body['error'] : wp_remote_retrieve_body( $response );
			$msg = sprintf( __( 'Bridge Error (HTTP %d): %s', 'wp-whatsapp-channel-autopublish' ), $code, $error_detail );
			$this->logger->log( 'error', 'Bridge Broadcast Failed', $msg, $post_id, 'Self-Hosted Bridge' );
			return array( 'success' => false, 'message' => $msg );
		}
	}

	/**
	 * Send via Evolution API
	 */
	protected function send_via_evolution( $base_url, $token, $channel_jid, $message, $image_url, $post_id ) {
		// Evolution API standard endpoint for text/media
		$headers = array(
			'apikey'       => $token,
			'Content-Type' => 'application/json',
		);

		if ( ! empty( $image_url ) ) {
			$endpoint = $base_url . '/message/sendMedia';
			$payload  = array(
				'number'    => $channel_jid,
				'media'     => $image_url,
				'mediatype' => 'image',
				'caption'   => $message,
			);
		} else {
			$endpoint = $base_url . '/message/sendText';
			$payload  = array(
				'number' => $channel_jid,
				'text'   => $message,
			);
		}

		$response = wp_remote_post( $endpoint, array(
			'headers'   => $headers,
			'body'      => wp_json_encode( $payload ),
			'timeout'   => 15,
			'sslverify' => true,
		) );

		if ( is_wp_error( $response ) ) {
			$err = $response->get_error_message();
			$this->logger->log( 'error', 'Evolution API Error', $err, $post_id, 'Evolution API' );
			return array( 'success' => false, 'message' => $err );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 ) {
			$msg = sprintf( __( 'Evolution API broadcasted to %s', 'wp-whatsapp-channel-autopublish' ), $channel_jid );
			$this->logger->log( 'success', 'Evolution Broadcast Success', $body, $post_id, 'Evolution API' );
			return array( 'success' => true, 'message' => $msg );
		} else {
			$msg = sprintf( __( 'Evolution API Error (HTTP %d): %s', 'wp-whatsapp-channel-autopublish' ), $code, wp_remote_retrieve_body( $response ) );
			$this->logger->log( 'error', 'Evolution Broadcast Failed', $msg, $post_id, 'Evolution API' );
			return array( 'success' => false, 'message' => $msg );
		}
	}
}
