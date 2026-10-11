<?php
/**
 * Official Meta WhatsApp Cloud API Client.
 * 
 * Uses Meta Graph API (v21.0) to send messages to phone numbers / subscriber lists.
 * Note: Official Meta Cloud API sends to E.164 phone numbers (individual recipients).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPWCA_Meta_API {

	/**
	 * @var WPWCA_Logger
	 */
	protected $logger;

	const GRAPH_API_VERSION = 'v21.0';

	public function __construct( WPWCA_Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Broadcast a message to the configured recipient phone numbers via Meta Cloud API.
	 *
	 * @param string      $message      Formatted text message.
	 * @param string|null $image_url    Optional featured image URL.
	 * @param int         $post_id      WordPress Post ID.
	 * @param string|null $post_url     WordPress Post URL.
	 * @param string|null $post_title   WordPress Post Title.
	 * @return array                    Array with 'success' (bool) and 'message' (string).
	 */
	public function broadcast( $message, $image_url = null, $post_id = 0, $post_url = '', $post_title = '' ) {
		$options = get_option( WPWCA_SETTINGS_KEY, array() );

		$access_token    = isset( $options['meta_access_token'] ) ? trim( $options['meta_access_token'] ) : '';
		$phone_number_id = isset( $options['meta_phone_number_id'] ) ? trim( $options['meta_phone_number_id'] ) : '';
		$recipients_raw  = isset( $options['meta_recipient_numbers'] ) ? trim( $options['meta_recipient_numbers'] ) : '';
		$message_mode    = isset( $options['meta_message_mode'] ) ? $options['meta_message_mode'] : 'text';

		if ( empty( $access_token ) || empty( $phone_number_id ) ) {
			$err = __( 'Meta Cloud API Access Token or Phone Number ID is missing in settings.', 'wp-whatsapp-channel-autopublish' );
			$this->logger->log( 'error', 'Meta Cloud Broadcast Failed', $err, $post_id, 'Meta Cloud API' );
			return array( 'success' => false, 'message' => $err );
		}

		if ( empty( $recipients_raw ) ) {
			$err = __( 'No recipient phone numbers configured for Meta Cloud API.', 'wp-whatsapp-channel-autopublish' );
			$this->logger->log( 'error', 'Meta Cloud Broadcast Failed', $err, $post_id, 'Meta Cloud API' );
			return array( 'success' => false, 'message' => $err );
		}

		// Split recipient numbers by comma, newline, or space
		$recipients = preg_split( '/[\r\n,]+/', $recipients_raw );
		$recipients = array_filter( array_map( 'trim', $recipients ) );

		if ( empty( $recipients ) ) {
			$err = __( 'No valid recipient phone numbers found.', 'wp-whatsapp-channel-autopublish' );
			return array( 'success' => false, 'message' => $err );
		}

		$endpoint = sprintf( 'https://graph.facebook.com/%s/%s/messages', self::GRAPH_API_VERSION, $phone_number_id );
		$headers  = array(
			'Authorization' => 'Bearer ' . $access_token,
			'Content-Type'  => 'application/json',
		);

		$success_count = 0;
		$fail_count    = 0;
		$last_error    = '';

		foreach ( $recipients as $recipient ) {
			// Clean up phone number (remove +, spaces, hyphens)
			$clean_number = preg_replace( '/[^0-9]/', '', $recipient );
			if ( empty( $clean_number ) ) {
				continue;
			}

			if ( 'template' === $message_mode && ! empty( $options['meta_template_name'] ) ) {
				$payload = array(
					'messaging_product' => 'whatsapp',
					'recipient_type'    => 'individual',
					'to'                => $clean_number,
					'type'              => 'template',
					'template'          => array(
						'name'     => sanitize_text_field( $options['meta_template_name'] ),
						'language' => array(
							'code' => ! empty( $options['meta_template_language'] ) ? sanitize_text_field( $options['meta_template_language'] ) : 'en_US',
						),
						'components' => array(
							array(
								'type'       => 'body',
								'parameters' => array(
									array( 'type' => 'text', 'text' => $post_title ? $post_title : 'New Post' ),
									array( 'type' => 'text', 'text' => $post_url ? $post_url : home_url() ),
								),
							),
						),
					),
				);
			} elseif ( ! empty( $image_url ) ) {
				$payload = array(
					'messaging_product' => 'whatsapp',
					'recipient_type'    => 'individual',
					'to'                => $clean_number,
					'type'              => 'image',
					'image'             => array(
						'link'    => $image_url,
						'caption' => $message,
					),
				);
			} else {
				$payload = array(
					'messaging_product' => 'whatsapp',
					'recipient_type'    => 'individual',
					'to'                => $clean_number,
					'type'              => 'text',
					'text'              => array(
						'preview_url' => true,
						'body'        => $message,
					),
				);
			}

			$response = wp_remote_post( $endpoint, array(
				'headers'   => $headers,
				'body'      => wp_json_encode( $payload ),
				'timeout'   => 15,
				'sslverify' => true,
			) );

			if ( is_wp_error( $response ) ) {
				$fail_count++;
				$last_error = $response->get_error_message();
			} else {
				$code = wp_remote_retrieve_response_code( $response );
				$body = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( $code >= 200 && $code < 300 ) {
					$success_count++;
				} else {
					$fail_count++;
					$last_error = isset( $body['error']['message'] ) ? $body['error']['message'] : wp_remote_retrieve_body( $response );
				}
			}
		}

		if ( $success_count > 0 && 0 === $fail_count ) {
			$msg = sprintf( __( 'Successfully sent to %d recipient(s) via Meta Cloud API.', 'wp-whatsapp-channel-autopublish' ), $success_count );
			$this->logger->log( 'success', 'Meta Cloud Broadcast Success', $msg, $post_id, 'Meta Cloud API' );
			return array( 'success' => true, 'message' => $msg );
		} elseif ( $success_count > 0 && $fail_count > 0 ) {
			$msg = sprintf( __( 'Partial success: %d sent, %d failed. Last error: %s', 'wp-whatsapp-channel-autopublish' ), $success_count, $fail_count, $last_error );
			$this->logger->log( 'info', 'Meta Cloud Partial Broadcast', $msg, $post_id, 'Meta Cloud API' );
			return array( 'success' => true, 'message' => $msg );
		} else {
			$msg = sprintf( __( 'Meta Cloud Broadcast Failed for %d recipient(s). Error: %s', 'wp-whatsapp-channel-autopublish' ), $fail_count, $last_error );
			$this->logger->log( 'error', 'Meta Cloud Broadcast Failed', $msg, $post_id, 'Meta Cloud API' );
			return array( 'success' => false, 'message' => $msg );
		}
	}
}
