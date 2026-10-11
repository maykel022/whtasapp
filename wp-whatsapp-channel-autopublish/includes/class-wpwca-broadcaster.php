<?php
/**
 * Post Broadcaster Class.
 * 
 * Intercepts post publishing transitions, parses content, and dispatches to the selected provider.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPWCA_Broadcaster {

	/**
	 * @var WPWCA_Meta_API
	 */
	protected $meta_api;

	/**
	 * @var WPWCA_Channel_API
	 */
	protected $channel_api;

	/**
	 * @var WPWCA_Logger
	 */
	protected $logger;

	public function __construct( WPWCA_Meta_API $meta_api, WPWCA_Channel_API $channel_api, WPWCA_Logger $logger ) {
		$this->meta_api    = $meta_api;
		$this->channel_api = $channel_api;
		$this->logger      = $logger;
	}

	/**
	 * Get Meta API instance.
	 *
	 * @return WPWCA_Meta_API
	 */
	public function get_meta_api() {
		return $this->meta_api;
	}

	/**
	 * Get Channel API instance.
	 *
	 * @return WPWCA_Channel_API
	 */
	public function get_channel_api() {
		return $this->channel_api;
	}

	/**
	 * Register WordPress hooks.
	 */
	public function init_hooks() {
		add_action( 'transition_post_status', array( $this, 'handle_post_transition' ), 10, 3 );
	}

	/**
	 * Handle post status transition.
	 *
	 * @param string  $new_status
	 * @param string  $old_status
	 * @param WP_Post $post
	 */
	public function handle_post_transition( $new_status, $old_status, $post ) {
		// Only run when changing to 'publish' from a non-publish status
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}

		if ( ! $post || ! ( $post instanceof WP_Post ) ) {
			return;
		}

		// Prevent infinite loops or autosaves/revisions
		if ( wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) ) {
			return;
		}

		$options = get_option( WPWCA_SETTINGS_KEY, array() );

		// Check if auto-broadcast is enabled
		if ( isset( $options['auto_broadcast_on_publish'] ) && 'no' === $options['auto_broadcast_on_publish'] ) {
			return;
		}

		// Check if this post type is supported
		$supported_types = isset( $options['post_types'] ) && is_array( $options['post_types'] ) ? $options['post_types'] : array( 'post' );
		if ( ! in_array( $post->post_type, $supported_types, true ) ) {
			return;
		}

		// Check individual post override meta (if unchecked on post edit screen)
		$post_setting = get_post_meta( $post->ID, '_wpwca_enable_broadcast', true );
		if ( 'no' === $post_setting ) {
			return;
		}

		// Prevent duplicate broadcast if already broadcasted
		$already_broadcasted = get_post_meta( $post->ID, '_wpwca_broadcasted', true );
		if ( ! empty( $already_broadcasted ) ) {
			return;
		}

		// Dispatch broadcast
		$this->broadcast_post( $post->ID, false );
	}

	/**
	 * Broadcast a single post.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $force   Whether to force broadcast even if already sent.
	 * @return array Array with success status and message.
	 */
	public function broadcast_post( $post_id, $force = false ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'success' => false, 'message' => __( 'Invalid Post ID', 'wp-whatsapp-channel-autopublish' ) );
		}

		if ( ! $force ) {
			$already = get_post_meta( $post_id, '_wpwca_broadcasted', true );
			if ( ! empty( $already ) ) {
				return array( 'success' => false, 'message' => __( 'Post already broadcasted previously.', 'wp-whatsapp-channel-autopublish' ) );
			}
		}

		$options = get_option( WPWCA_SETTINGS_KEY, array() );
		$mode    = isset( $options['provider_mode'] ) ? $options['provider_mode'] : 'channel_gateway';

		// Prepare message content
		$message = $this->prepare_message( $post, $options );

		// Prepare featured image
		$image_url = null;
		if ( isset( $options['include_featured_image'] ) && 'yes' === $options['include_featured_image'] ) {
			if ( has_post_thumbnail( $post_id ) ) {
				$img_src = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'large' );
				if ( ! empty( $img_src[0] ) ) {
					$image_url = $img_src[0];
				}
			}
		}

		$post_url   = get_permalink( $post_id );
		$post_title = get_the_title( $post_id );

		// Dispatch based on provider mode
		if ( 'meta_cloud' === $mode ) {
			$result = $this->meta_api->broadcast( $message, $image_url, $post_id, $post_url, $post_title );
		} else {
			// WhatsApp Channel Gateway (Whapi.cloud / Bridge / Evolution)
			$result = $this->channel_api->broadcast( $message, $image_url, $post_id );
		}

		// Record result in post meta
		if ( $result['success'] ) {
			update_post_meta( $post_id, '_wpwca_broadcasted', current_time( 'mysql' ) );
			update_post_meta( $post_id, '_wpwca_last_status', 'success' );
		} else {
			update_post_meta( $post_id, '_wpwca_last_status', 'failed' );
			update_post_meta( $post_id, '_wpwca_last_error', $result['message'] );
		}

		return $result;
	}

	/**
	 * Prepare formatted message text using templates and post data.
	 *
	 * @param WP_Post $post
	 * @param array   $options
	 * @return string
	 */
	public function prepare_message( $post, $options = array() ) {
		$template = ! empty( $options['message_template'] )
			? $options['message_template']
			: "🚀 *New Post:* {title}\n\n{excerpt}\n\n📖 Read full article:\n{url}";

		// Extract post data
		$title     = html_entity_decode( get_the_title( $post->ID ), ENT_QUOTES, 'UTF-8' );
		$url       = get_permalink( $post->ID );
		$author    = get_the_author_meta( 'display_name', $post->post_author );
		$date      = get_the_date( '', $post->ID );

		// Excerpt handling
		if ( ! empty( $post->post_excerpt ) ) {
			$excerpt = wp_strip_all_tags( $post->post_excerpt );
		} else {
			$excerpt = wp_trim_words( wp_strip_all_tags( $post->post_content ), 35, '...' );
		}

		// Categories
		$categories_list = get_the_category( $post->ID );
		$categories_str  = '';
		if ( ! empty( $categories_list ) && ! is_wp_error( $categories_list ) ) {
			$cat_names      = wp_list_pluck( $categories_list, 'name' );
			$categories_str = implode( ', ', $cat_names );
		}

		// Tags
		$tags_list = get_the_tags( $post->ID );
		$tags_str  = '';
		if ( ! empty( $tags_list ) && ! is_wp_error( $tags_list ) ) {
			$tag_names = wp_list_pluck( $tags_list, 'name' );
			$tags_str  = '#' . implode( ' #', $tag_names );
		}

		// Replace tags
		$replacements = array(
			'{title}'      => $title,
			'{url}'        => $url,
			'{excerpt}'    => $excerpt,
			'{author}'     => $author,
			'{date}'       => $date,
			'{categories}' => $categories_str,
			'{tags}'       => $tags_str,
		);

		$message = str_replace( array_keys( $replacements ), array_values( $replacements ), $template );

		return trim( $message );
	}
}
