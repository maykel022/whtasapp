<?php
/**
 * Admin UI and AJAX Handlers for WhatsApp Channel AutoPublish.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPWCA_Admin {

	/**
	 * @var WPWCA_Broadcaster
	 */
	protected $broadcaster;

	/**
	 * @var WPWCA_Logger
	 */
	protected $logger;

	public function __construct( WPWCA_Broadcaster $broadcaster, WPWCA_Logger $logger ) {
		$this->broadcaster = $broadcaster;
		$this->logger      = $logger;
	}

	/**
	 * Register Admin hooks.
	 */
	public function init_hooks() {
		// Menu hooks
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'init', array( $this, 'register_post_meta' ) );

		// Asset enqueue hooks
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );

		// Classic Post Meta Box (Fallback for classic editor)
		add_action( 'add_meta_boxes', array( $this, 'add_post_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_post_meta_box' ) );

		// AJAX Handlers
		add_action( 'wp_ajax_wpwca_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_wpwca_manual_broadcast', array( $this, 'ajax_manual_broadcast' ) );
		add_action( 'wp_ajax_wpwca_clear_logs', array( $this, 'ajax_clear_logs' ) );
	}

	/**
	 * Register custom post meta for Gutenberg REST API.
	 */
	public function register_post_meta() {
		$options = get_option( WPWCA_SETTINGS_KEY, array() );
		$types   = isset( $options['post_types'] ) && is_array( $options['post_types'] ) && ! empty( $options['post_types'] )
			? $options['post_types']
			: array( 'post' );

		foreach ( $types as $post_type ) {
			register_post_meta( $post_type, '_wpwca_enable_broadcast', array(
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'string',
				'default'       => 'yes',
				'auth_callback' => function() {
					return current_user_can( 'edit_posts' );
				},
			) );

			register_post_meta( $post_type, '_wpwca_broadcasted', array(
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'string',
				'auth_callback' => function() {
					return current_user_can( 'edit_posts' );
				},
			) );

			register_post_meta( $post_type, '_wpwca_last_status', array(
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'string',
				'auth_callback' => function() {
					return current_user_can( 'edit_posts' );
				},
			) );

			register_post_meta( $post_type, '_wpwca_last_error', array(
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'string',
				'auth_callback' => function() {
					return current_user_can( 'edit_posts' );
				},
			) );
		}
	}

	/**
	 * Add Menu in WordPress Admin:
	 * 1. Top-Level item in WordPress main left sidebar menu (WhatsApp).
	 * 2. Settings item under Settings > WhatsApp AutoPublish.
	 */
	public function add_admin_menu() {
		// 1. Top-level item in WordPress left sidebar
		add_menu_page(
			__( 'WhatsApp AutoPublish', 'wp-whatsapp-channel-autopublish' ),
			__( 'WhatsApp', 'wp-whatsapp-channel-autopublish' ),
			'manage_options',
			'wp-whatsapp-autopublish',
			array( $this, 'render_settings_page' ),
			'dashicons-share',
			28
		);

		// 2. Submenu under WhatsApp
		add_submenu_page(
			'wp-whatsapp-autopublish',
			__( 'WhatsApp AutoPublish Settings', 'wp-whatsapp-channel-autopublish' ),
			__( 'Settings', 'wp-whatsapp-channel-autopublish' ),
			'manage_options',
			'wp-whatsapp-autopublish',
			array( $this, 'render_settings_page' )
		);

		// 3. Keep Settings > WhatsApp AutoPublish as well
		add_options_page(
			__( 'WhatsApp AutoPublish Settings', 'wp-whatsapp-channel-autopublish' ),
			__( 'WhatsApp AutoPublish', 'wp-whatsapp-channel-autopublish' ),
			'manage_options',
			'wp-whatsapp-autopublish-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting( 'wpwca_settings_group', WPWCA_SETTINGS_KEY, array(
			'sanitize_callback' => array( $this, 'sanitize_settings' ),
		) );
	}

	/**
	 * Sanitize submitted settings.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		$sanitized['provider_mode'] = isset( $input['provider_mode'] ) && in_array( $input['provider_mode'], array( 'channel_gateway', 'meta_cloud' ), true )
			? $input['provider_mode']
			: 'channel_gateway';

		// Channel Gateway
		$sanitized['channel_gateway_type']  = sanitize_key( $input['channel_gateway_type'] ?? 'whapi' );
		$sanitized['channel_gateway_token'] = sanitize_text_field( $input['channel_gateway_token'] ?? '' );
		$sanitized['channel_gateway_jid']   = sanitize_text_field( $input['channel_gateway_jid'] ?? '' );
		$sanitized['channel_gateway_url']   = esc_url_raw( $input['channel_gateway_url'] ?? 'https://gate.whapi.cloud' );

		// Meta Cloud API
		$sanitized['meta_access_token']      = sanitize_text_field( $input['meta_access_token'] ?? '' );
		$sanitized['meta_phone_number_id']   = sanitize_text_field( $input['meta_phone_number_id'] ?? '' );
		$sanitized['meta_recipient_numbers'] = sanitize_textarea_field( $input['meta_recipient_numbers'] ?? '' );
		$sanitized['meta_message_mode']      = sanitize_key( $input['meta_message_mode'] ?? 'text' );
		$sanitized['meta_template_name']     = sanitize_text_field( $input['meta_template_name'] ?? '' );
		$sanitized['meta_template_language'] = sanitize_text_field( $input['meta_template_language'] ?? 'en_US' );

		// Content
		$sanitized['auto_broadcast_on_publish'] = ! empty( $input['auto_broadcast_on_publish'] ) ? 'yes' : 'no';
		$sanitized['include_featured_image']    = ! empty( $input['include_featured_image'] ) ? 'yes' : 'no';
		$sanitized['message_template']          = sanitize_textarea_field( $input['message_template'] ?? '' );
		$sanitized['enable_logging']            = ! empty( $input['enable_logging'] ) ? 'yes' : 'no';

		// Post Types
		if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
			$sanitized['post_types'] = array_map( 'sanitize_key', $input['post_types'] );
		} else {
			$sanitized['post_types'] = array( 'post' );
		}

		return $sanitized;
	}

	/**
	 * Enqueue scripts and styles for admin settings and classic post edit.
	 */
	public function enqueue_admin_assets( $hook ) {
		$screen = get_current_screen();
		$is_settings_page = (
			'toplevel_page_wp-whatsapp-autopublish' === $hook ||
			'whatsapp_page_wp-whatsapp-autopublish' === $hook ||
			'settings_page_wp-whatsapp-autopublish' === $hook ||
			'settings_page_wp-whatsapp-autopublish-settings' === $hook ||
			( isset( $_GET['page'] ) && ( 'wp-whatsapp-autopublish' === $_GET['page'] || 'wp-whatsapp-autopublish-settings' === $_GET['page'] ) )
		);
		$is_post_edit = $screen && 'post' === $screen->base;

		if ( ! $is_settings_page && ! $is_post_edit ) {
			return;
		}

		wp_enqueue_style(
			'wpwca-admin-css',
			WPWCA_PLUGIN_URL . 'assets/admin.css',
			array(),
			WPWCA_VERSION
		);

		wp_enqueue_script(
			'wpwca-admin-js',
			WPWCA_PLUGIN_URL . 'assets/admin.js',
			array( 'jquery' ),
			WPWCA_VERSION,
			true
		);

		wp_localize_script( 'wpwca-admin-js', 'wpwcaAdmin', array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'wpwca_admin_nonce' ),
			'strings'   => array(
				'testing'      => __( 'Testing WhatsApp connection...', 'wp-whatsapp-channel-autopublish' ),
				'broadcasting' => __( 'Broadcasting to WhatsApp...', 'wp-whatsapp-channel-autopublish' ),
				'clearing'     => __( 'Clearing logs...', 'wp-whatsapp-channel-autopublish' ),
				'confirmClear' => __( 'Are you sure you want to clear all WhatsApp broadcast logs?', 'wp-whatsapp-channel-autopublish' ),
			),
		) );
	}

	/**
	 * Enqueue Gutenberg Block Editor Sidebar Panel Script.
	 */
	public function enqueue_block_editor_assets() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$options = get_option( WPWCA_SETTINGS_KEY, array() );
		$types   = isset( $options['post_types'] ) && is_array( $options['post_types'] ) && ! empty( $options['post_types'] )
			? $options['post_types']
			: array( 'post' );

		if ( ! in_array( $screen->post_type, $types, true ) ) {
			return;
		}

		wp_enqueue_style(
			'wpwca-admin-css',
			WPWCA_PLUGIN_URL . 'assets/admin.css',
			array(),
			WPWCA_VERSION
		);

		wp_enqueue_script(
			'wpwca-gutenberg-sidebar',
			WPWCA_PLUGIN_URL . 'assets/gutenberg-sidebar.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-dom-ready', 'jquery' ),
			WPWCA_VERSION,
			true
		);

		wp_localize_script( 'wpwca-gutenberg-sidebar', 'wpwcaAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wpwca_admin_nonce' ),
		) );
	}

	/**
	 * Render Post Edit Meta Box.
	 */
	public function add_post_meta_box() {
		$options = get_option( WPWCA_SETTINGS_KEY, array() );
		$types   = isset( $options['post_types'] ) && is_array( $options['post_types'] ) && ! empty( $options['post_types'] )
			? $options['post_types']
			: array( 'post' );

		foreach ( $types as $post_type ) {
			add_meta_box(
				'wpwca_post_meta_box',
				__( 'WhatsApp Broadcast', 'wp-whatsapp-channel-autopublish' ),
				array( $this, 'render_post_meta_box' ),
				$post_type,
				'side',
				'high'
			);
		}
	}

	/**
	 * Render Post Meta Box HTML.
	 */
	public function render_post_meta_box( $post ) {
		wp_nonce_field( 'wpwca_save_post_meta', 'wpwca_post_nonce' );

		$broadcasted = get_post_meta( $post->ID, '_wpwca_broadcasted', true );
		$last_status = get_post_meta( $post->ID, '_wpwca_last_status', true );
		$last_error  = get_post_meta( $post->ID, '_wpwca_last_error', true );
		$enabled     = get_post_meta( $post->ID, '_wpwca_enable_broadcast', true );

		if ( '' === $enabled ) {
			$enabled = 'yes';
		}
		?>
		<div class="wpwca-metabox-content">
			<label class="wpwca-checkbox-label">
				<input type="checkbox" name="_wpwca_enable_broadcast" value="yes" <?php checked( 'yes', $enabled ); ?> />
				<strong><?php esc_html_e( 'Broadcast on publish', 'wp-whatsapp-channel-autopublish' ); ?></strong>
			</label>

			<div class="wpwca-status-area" style="margin-top: 10px;">
				<?php if ( ! empty( $broadcasted ) ) : ?>
					<div class="wpwca-badge wpwca-badge-success">
						<span class="dashicons dashicons-yes-alt"></span>
						<?php printf( esc_html__( 'Broadcasted: %s', 'wp-whatsapp-channel-autopublish' ), esc_html( $broadcasted ) ); ?>
					</div>
				<?php elseif ( 'failed' === $last_status ) : ?>
					<div class="wpwca-badge wpwca-badge-error">
						<span class="dashicons dashicons-warning"></span>
						<?php esc_html_e( 'Broadcast Failed', 'wp-whatsapp-channel-autopublish' ); ?>
					</div>
					<?php if ( ! empty( $last_error ) ) : ?>
						<p class="wpwca-error-detail" style="font-size: 11px; color: #d63638; margin: 4px 0 0 0;"><?php echo esc_html( $last_error ); ?></p>
					<?php endif; ?>
				<?php else : ?>
					<div class="wpwca-badge wpwca-badge-pending">
						<span class="dashicons dashicons-clock"></span>
						<?php esc_html_e( 'Not yet broadcasted', 'wp-whatsapp-channel-autopublish' ); ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="wpwca-action-area" style="margin-top: 12px; padding-top: 10px; border-top: 1px solid #ddd;">
				<button type="button" class="button button-secondary wpwca-manual-broadcast-btn" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
					<span class="dashicons dashicons-share-alt" style="vertical-align: middle; margin-top: -2px;"></span>
					<?php esc_html_e( 'Broadcast to WhatsApp Now', 'wp-whatsapp-channel-autopublish' ); ?>
				</button>
				<div class="wpwca-manual-response" style="margin-top: 8px; font-size: 12px; display: none;"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save Post Meta Box Settings.
	 */
	public function save_post_meta_box( $post_id ) {
		if ( ! isset( $_POST['wpwca_post_nonce'] ) || ! wp_verify_nonce( $_POST['wpwca_post_nonce'], 'wpwca_save_post_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$enabled = isset( $_POST['_wpwca_enable_broadcast'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_wpwca_enable_broadcast', $enabled );
	}

	/**
	 * AJAX handler: Test Connection / Broadcast
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'wpwca_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-whatsapp-channel-autopublish' ) ) );
		}

		$options = get_option( WPWCA_SETTINGS_KEY, array() );
		$mode    = isset( $options['provider_mode'] ) ? $options['provider_mode'] : 'channel_gateway';

		$test_message = sprintf(
			"🧪 *WhatsApp AutoPublish Test Message*\n\nWebsite: %s\nTime: %s\n\n✅ Your integration is working perfectly!",
			get_bloginfo( 'name' ),
			current_time( 'Y-m-d H:i:s' )
		);

		if ( 'meta_cloud' === $mode ) {
			$result = $this->broadcaster->get_meta_api()->broadcast( $test_message, null, 0, home_url(), 'Test Message' );
		} else {
			$result = $this->broadcaster->get_channel_api()->broadcast( $test_message, null, 0 );
		}

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => $result['message'] ) );
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
	}

	/**
	 * AJAX handler: Manual Broadcast Post
	 */
	public function ajax_manual_broadcast() {
		check_ajax_referer( 'wpwca_admin_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request or permission denied.', 'wp-whatsapp-channel-autopublish' ) ) );
		}

		// Force broadcast regardless of previous status
		$result = $this->broadcaster->broadcast_post( $post_id, true );

		if ( $result['success'] ) {
			wp_send_json_success( array(
				'message'   => $result['message'],
				'timestamp' => current_time( 'mysql' ),
			) );
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
	}

	/**
	 * AJAX handler: Clear Broadcast Logs
	 */
	public function ajax_clear_logs() {
		check_ajax_referer( 'wpwca_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-whatsapp-channel-autopublish' ) ) );
		}

		$this->logger->clear_logs();
		wp_send_json_success( array( 'message' => __( 'Logs cleared successfully.', 'wp-whatsapp-channel-autopublish' ) ) );
	}

	/**
	 * Render Admin Settings Page HTML.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options = get_option( WPWCA_SETTINGS_KEY, WPWCA_Activator::get_default_options() );
		$logs    = $this->logger->get_logs();
		?>
		<div class="wrap wpwca-admin-wrap">
			<h1>
				<span class="dashicons dashicons-megaphone" style="font-size: 32px; width: 32px; height: 32px; vertical-align: middle; color: #25D366;"></span>
				<?php esc_html_e( 'WhatsApp AutoPublish Settings', 'wp-whatsapp-channel-autopublish' ); ?>
			</h1>

			<div class="wpwca-notice-box">
				<p>
					<strong>📢 <?php esc_html_e( 'WhatsApp Channels vs. Meta Cloud API:', 'wp-whatsapp-channel-autopublish' ); ?></strong>
					<?php esc_html_e( 'Public WhatsApp Channels (under WhatsApp Updates with @newsletter ID) require the Channel Gateway mode (Whapi.cloud or Self-Hosted Bridge). Meta Cloud API is designed for sending to subscriber phone numbers.', 'wp-whatsapp-channel-autopublish' ); ?>
				</p>
			</div>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'wpwca_settings_group' );
				?>

				<div class="wpwca-layout">
					<!-- Main Column -->
					<div class="wpwca-main-col">

						<!-- Card 1: Provider Selection -->
						<div class="wpwca-card">
							<h2><?php esc_html_e( '1. Select Delivery Method', 'wp-whatsapp-channel-autopublish' ); ?></h2>
							<div class="wpwca-radio-group">
								<label class="wpwca-radio-option">
									<input type="radio" name="<?php echo esc_attr( WPWCA_SETTINGS_KEY ); ?>[provider_mode]" value="channel_gateway" <?php checked( 'channel_gateway', $options['provider_mode'] ); ?> />
									<div class="wpwca-radio-text">
										<strong><?php esc_html_e( 'WhatsApp Channel Broadcast (Recommended for Channels)', 'wp-whatsapp-channel-autopublish' ); ?></strong>
										<span><?php esc_html_e( 'Publishes directly to a public WhatsApp Channel feed (ends in @newsletter) using Whapi.cloud, Evolution API, or your local bridge.', 'wp-whatsapp-channel-autopublish' ); ?></span>
									</div>
								</label>

								<label class="wpwca-radio-option">
									<input type="radio" name="<?php echo esc_attr( WPWCA_SETTINGS_KEY ); ?>[provider_mode]" value="meta_cloud" <?php checked( 'meta_cloud', $options['provider_mode'] ); ?> />
									<div class="wpwca-radio-text">
										<strong><?php esc_html_e( 'Official Meta WhatsApp Cloud API (Graph API)', 'wp-whatsapp-channel-autopublish' ); ?></strong>
										<span><?php esc_html_e( 'Sends post updates to subscriber phone numbers using Meta Business Platform Cloud API.', 'wp-whatsapp-channel-autopublish' ); ?></span>
									</div>
								</label>
							</div>
						</div>

						<!-- Card 2A: WhatsApp Channel Gateway Configuration -->
						<div class="wpwca-card wpwca-provider-section" id="wpwca-channel-config" style="<?php echo 'channel_gateway' === $options['provider_mode'] ? '' : 'display:none;'; ?>">
							<h2><?php esc_html_e( '2. WhatsApp Channel Settings', 'wp-whatsapp-channel-autopublish' ); ?></h2>

							<table class="form-table">
								<tr>
									<th scope="row"><label for="channel_gateway_type"><?php esc_html_e( 'Gateway Provider', 'wp-whatsapp-channel-autopublish' ); ?></label></th>
									<td>
										<select name="<?php echo esc_attr( WPWCA_SETTINGS_KEY ); ?>[channel_gateway_type]" id="channel_gateway_type">
											<option value="bridge" <?php selected( 'bridge', $options['channel_gateway_type'] ); ?>>Self-Hosted Node.js Bridge (Recommended - 100% Free & Private)</option>
											<option value="whapi" <?php selected( 'whapi', $options['channel_gateway_type'] ); ?>>Whapi.cloud (Cloud SaaS)</option>
											<option value="evolution" <?php selected( 'evolution', $options['channel_gateway_type'] ); ?>>Evolution API</option>
										</select>
										<p class="description"><?php esc_html_e( 'Choose the self-hosted bridge (runs in /bridge folder) or an external gateway to communicate with your WhatsApp Channel.', 'wp-whatsapp-channel-autopublish' ); ?></p>
									</td>
								</tr>

								<tr class="wpwca-row-token">
									<th scope="row"><label for="channel_gateway_token"><?php esc_html_e( 'API Token / Secret', 'wp-whatsapp-channel-autopublish' ); ?></label></th>
									<td>
										<input type="password" name="<?php echo esc_attr( WPWCA_SETTINGS_KEY ); ?>[channel_gateway_token]" id="channel_gateway_token" value="<?php echo esc_attr( $options['channel_gateway_token'] ); ?>" class="regular-text" />
										<p class="description"><?php esc_html_e( 'Optional API Secret (matches API_SECRET in bridge/.env). Leave blank if using default open local bridge.', 'wp-whatsapp-channel-autopublish' ); ?></p>
									</td>
								</tr>

								<tr>
									<th scope="row"><label for="channel_gateway_jid"><?php esc_html_e( 'Channel ID / JID', 'wp-whatsapp-channel-autopublish' ); ?> <span class="required">*</span></label></th>
									<td>
										<input type="text" name="<?php echo esc_attr( WPWCA_SETTINGS_KEY ); ?>[channel_gateway_jid]" id="channel_gateway_jid" value="<?php echo esc_attr( $options['channel_gateway_jid'] ); ?>" class="regular-text" placeholder="120363171744447809@newsletter" />
										<p class="description">
											<?php esc_html_e( 'The WhatsApp Channel JID (e.g. 120363xxxxxxxxxx@newsletter). You can find it instantly by pasting your channel invite link in the Self-Hosted Bridge web dashboard at http://localhost:3000.', 'wp-whatsapp-channel-autopublish' ); ?>
										</p>
									</td>
								</tr>

								<tr>
									<th scope="row"><label for="channel_gateway_url"><?php esc_html_e( 'Bridge / Gateway URL', 'wp-whatsapp-channel-autopublish' ); ?></label></th>
									<td>
										<input type="url" name="<?php echo esc_attr( WPWCA_SETTINGS_KEY ); ?>[channel_gateway_url]" id="channel_gateway_url" value="<?php echo esc_attr( $options['channel_gateway_url'] ); ?>" class="regular-text" placeholder="http://localhost:3000" />
										<p class="description"><?php esc_html_e( 'Default is http://localhost:3000 for your local self-hosted bridge (or http://your-vps-ip:3000).', 'wp-whatsapp-channel-autopublish' ); ?></p>
									</td>
								</tr>
							</table>
						</div>

						<!-- Card 2B: Meta Cloud API Configuration -->
						<div class="wpwca-card wpwca-provider-section" id="wpwca-meta-config" style="<?php echo 'meta_cloud' === $options['provider_mode'] ? '' : 'display:none;'; ?>">
							<h2><?php esc_html_e( '2. Meta WhatsApp Cloud API Settings', 'wp-whatsapp-channel-autopublish' ); ?></h2>

							<table class="form-table">
								<tr>
									<th scope="row"><label for="meta_access_token"><?php esc_html_e( 'System User Access Token', 'wp-whatsapp-channel-autopublish' ); ?> <span class="required">*</span></label></th>
									<td>
										<input type="password" name="<?php echo esc_attr( WPWCA_SETTINGS_KEY ); ?>[meta_access_token]" id="meta_access_token" value="<?php echo esc_attr( $options['meta_access_token'] ); ?>" class="regular-text" />
										<p class="description"><?php esc_html_e( 'Permanent Access Token from Meta Developer App > WhatsApp > API Setup.', 'wp-whatsapp-channel-autopublish' ); ?></p>
									</td>
								</tr>

								<tr>
									<th scope="row"><label for="meta_phone_number_id"><?php esc_html_e( 'Phone Number ID', 'wp-whatsapp-channel-autopublish' ); ?> <span class="required">*</span></label></th>
									<td>
										<input type="text" name="<?php echo esc_attr( WPWCA_SETTINGS_KEY ); ?>[meta_phone_number_id]" id="meta_phone_number_id" value="<?php echo esc_attr( $options['meta_phone_number_id'] ); ?>" class="regular-text" placeholder="e.g. 104859604859604" />
										<p class="description"><?php esc_html_e( 'Phone Number ID from your Meta WhatsApp App dashboard (NOT your raw phone number).', 'wp-whatsapp-channel-autopublish' ); ?></p>
									</td>
								</tr>

								<tr>
									<th scope="row"><label for="meta_recipient_numbers"><?php esc_html_e( 'Recipient Phone Numbers', 'wp-whatsapp-channel-autopublish' ); ?> <span class="required">*</span></label></th>
									<td>
										<textarea name="<?php echo esc_attr( WPWCA_SETTINGS_KEY ); ?>[meta_recipient_numbers]" id="meta_recipient_numbers" rows="3" class="large-text" placeholder="+1234567890, +447911123456"><?php echo esc_textarea( $options['meta_recipient_numbers'] ); ?></textarea>
										<p class="description"><?php esc_html_e( 'Enter recipient numbers in international format with country code (e.g. +1234567890), comma or newline separated.', 'wp-whatsapp-channel-autopublish' ); ?></p>
									</td>
								</tr>

								<tr>
									<th scope="row"><label for="meta_message_mode"><?php esc_html_e( 'Message Type', 'wp-whatsapp-channel-autopublish' ); ?></label></th>
									<td>
										<select name="<?php echo esc_attr( WPWCA_SETTINGS_KEY ); ?>[meta_message_mode]" id="meta_message_mode">
											<option value="text" <?php selected( 'text', $options['meta_message_mode'] ); ?>>Session Text / Freeform (Requires 24-hr customer window)</option>
											<option value="template" <?php selected( 'template', $options['meta_message_mode'] ); ?>>Approved Meta Template (Recommended for broadcasts)</option>
										</select>
									</td>
								</tr>

								<tr class="wpwca-row-meta-template" style="<?php echo 'template' === $options['meta_message_mode'] ? '' : 'display:none;'; ?>">
									<th scope="row"><label for="meta_template_name"><?php esc_html_e( 'Template Name', 'wp-whatsapp-channel-autopublish' ); ?></label></th>
									<td>
										<input type="text" name="<?php echo esc_attr( WPWCA_SETTINGS_KEY ); ?>[meta_template_name]" id="meta_template_name" value="<?php echo esc_attr( $options['meta_template_name'] ); ?>" class="regular-text" placeholder="e.g. new_post_notification" />
										<p class="description"><?php esc_html_e( 'Pre-approved WhatsApp template name in your Meta WhatsApp Manager.', 'wp-whatsapp-channel-autopublish' ); ?></p>
									</td>
								</tr>
							</table>
						</div>

						<!-- Card 3: Post Publishing & Message Format -->
						<div class="wpwca-card">
							<h2><?php esc_html_e( '3. Content & Message Template', 'wp-whatsapp-channel-autopublish' ); ?></h2>

							<table class="form-table">
								<tr>
									<th scope="row"><?php esc_html_e( 'Auto-Broadcast', 'wp-whatsapp-channel-autopublish' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="<?php echo esc_attr( WPWCA_SETTINGS_KEY ); ?>[auto_broadcast_on_publish]" value="yes" <?php checked( 'yes', $options['auto_broadcast_on_publish'] ); ?> />
											<?php esc_html_e( 'Automatically broadcast new posts when they are published', 'wp-whatsapp-channel-autopublish' ); ?>
										</label>
									</td>
								</tr>

								<tr>
									<th scope="row"><?php esc_html_e( 'Featured Image', 'wp-whatsapp-channel-autopublish' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="<?php echo esc_attr( WPWCA_SETTINGS_KEY ); ?>[include_featured_image]" value="yes" <?php checked( 'yes', $options['include_featured_image'] ); ?> />
											<?php esc_html_e( 'Send the post featured image as photo with caption', 'wp-whatsapp-channel-autopublish' ); ?>
										</label>
									</td>
								</tr>

								<tr>
									<th scope="row"><?php esc_html_e( 'Post Types', 'wp-whatsapp-channel-autopublish' ); ?></th>
									<td>
										<?php
										$all_post_types = get_post_types( array( 'public' => true ), 'objects' );
										$selected_types = isset( $options['post_types'] ) && is_array( $options['post_types'] ) ? $options['post_types'] : array( 'post' );
										foreach ( $all_post_types as $pt ) :
											if ( 'attachment' === $pt->name ) continue;
											?>
											<label style="margin-right: 15px;">
												<input type="checkbox" name="<?php echo esc_attr( WPWCA_SETTINGS_KEY ); ?>[post_types][]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, $selected_types, true ) ); ?> />
												<?php echo esc_html( $pt->labels->singular_name ); ?> (<code><?php echo esc_html( $pt->name ); ?></code>)
											</label>
										<?php endforeach; ?>
									</td>
								</tr>

								<tr>
									<th scope="row"><label for="message_template"><?php esc_html_e( 'Message Template', 'wp-whatsapp-channel-autopublish' ); ?></label></th>
									<td>
										<textarea name="<?php echo esc_attr( WPWCA_SETTINGS_KEY ); ?>[message_template]" id="message_template" rows="6" class="large-text font-mono"><?php echo esc_textarea( $options['message_template'] ); ?></textarea>
										<div class="wpwca-token-tags" style="margin-top: 8px;">
											<span class="description"><?php esc_html_e( 'Click tokens to insert:', 'wp-whatsapp-channel-autopublish' ); ?></span>
											<button type="button" class="button button-small wpwca-insert-tag" data-tag="{title}">{title}</button>
											<button type="button" class="button button-small wpwca-insert-tag" data-tag="{url}">{url}</button>
											<button type="button" class="button button-small wpwca-insert-tag" data-tag="{excerpt}">{excerpt}</button>
											<button type="button" class="button button-small wpwca-insert-tag" data-tag="{author}">{author}</button>
											<button type="button" class="button button-small wpwca-insert-tag" data-tag="{categories}">{categories}</button>
											<button type="button" class="button button-small wpwca-insert-tag" data-tag="{tags}">{tags}</button>
											<button type="button" class="button button-small wpwca-insert-tag" data-tag="{date}">{date}</button>
										</div>
									</td>
								</tr>

								<tr>
									<th scope="row"><?php esc_html_e( 'Audit Logging', 'wp-whatsapp-channel-autopublish' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="<?php echo esc_attr( WPWCA_SETTINGS_KEY ); ?>[enable_logging]" value="yes" <?php checked( 'yes', $options['enable_logging'] ); ?> />
											<?php esc_html_e( 'Keep an activity log of recent broadcasts in WordPress', 'wp-whatsapp-channel-autopublish' ); ?>
										</label>
									</td>
								</tr>
							</table>
						</div>

						<p class="submit">
							<input type="submit" name="submit" id="submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Save Settings', 'wp-whatsapp-channel-autopublish' ); ?>" />
						</p>

					</div>

					<!-- Sidebar Column -->
					<div class="wpwca-side-col">

						<!-- Test Tool Card -->
						<div class="wpwca-card wpwca-card-sidebar">
							<h3><span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'Test WhatsApp Connection', 'wp-whatsapp-channel-autopublish' ); ?></h3>
							<p><?php esc_html_e( 'Send an immediate test broadcast to verify your settings before publishing live posts.', 'wp-whatsapp-channel-autopublish' ); ?></p>
							<button type="button" class="button button-secondary button-large wpwca-btn-full" id="wpwca-test-connection-btn">
								<span class="dashicons dashicons-controls-play" style="vertical-align: middle;"></span>
								<?php esc_html_e( 'Send Test Broadcast', 'wp-whatsapp-channel-autopublish' ); ?>
							</button>
							<div id="wpwca-test-result" style="margin-top: 10px; display: none;"></div>
						</div>

						<!-- Quick Help Card -->
						<div class="wpwca-card wpwca-card-sidebar">
							<h3><span class="dashicons dashicons-info"></span> <?php esc_html_e( 'WhatsApp Channel Guide', 'wp-whatsapp-channel-autopublish' ); ?></h3>
							<ol style="margin-left: 18px; line-height: 1.6; font-size: 13px;">
								<li><strong>Create Channel:</strong> In WhatsApp app, go to <em>Updates > Channels > Create Channel</em>.</li>
								<li><strong>Get Channel JID:</strong> In Whapi.cloud, use <code>GET /newsletters</code> to find the JID (e.g. <code>120363...@newsletter</code>).</li>
								<li><strong>Paste Token & JID:</strong> Paste them into the fields on the left and click Save Settings.</li>
								<li><strong>Test:</strong> Click the Test button above!</li>
							</ol>
						</div>

					</div>
				</div>
			</form>

			<!-- Logs Section -->
			<div class="wpwca-card wpwca-logs-card" style="margin-top: 25px;">
				<div class="wpwca-logs-header">
					<h2>
						<span class="dashicons dashicons-list-view"></span>
						<?php esc_html_e( 'Broadcast Activity Log (Last 50 Events)', 'wp-whatsapp-channel-autopublish' ); ?>
					</h2>
					<?php if ( ! empty( $logs ) ) : ?>
						<button type="button" class="button button-secondary" id="wpwca-clear-logs-btn">
							<span class="dashicons dashicons-trash" style="vertical-align: middle;"></span>
							<?php esc_html_e( 'Clear Logs', 'wp-whatsapp-channel-autopublish' ); ?>
						</button>
					<?php endif; ?>
				</div>

				<?php if ( empty( $logs ) ) : ?>
					<p style="color: #666; font-style: italic; margin-top: 10px;"><?php esc_html_e( 'No broadcast activity recorded yet.', 'wp-whatsapp-channel-autopublish' ); ?></p>
				<?php else : ?>
					<div class="wpwca-table-wrap" style="overflow-x: auto; margin-top: 12px;">
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th style="width: 140px;"><?php esc_html_e( 'Timestamp', 'wp-whatsapp-channel-autopublish' ); ?></th>
									<th style="width: 100px;"><?php esc_html_e( 'Status', 'wp-whatsapp-channel-autopublish' ); ?></th>
									<th style="width: 130px;"><?php esc_html_e( 'Provider', 'wp-whatsapp-channel-autopublish' ); ?></th>
									<th style="width: 200px;"><?php esc_html_e( 'Action / Title', 'wp-whatsapp-channel-autopublish' ); ?></th>
									<th><?php esc_html_e( 'Details / Response', 'wp-whatsapp-channel-autopublish' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $logs as $log ) : ?>
									<tr>
										<td><code><?php echo esc_html( $log['time'] ); ?></code></td>
										<td>
											<?php if ( 'success' === $log['status'] ) : ?>
												<span class="wpwca-status-pill wpwca-pill-success">Success</span>
											<?php elseif ( 'error' === $log['status'] ) : ?>
												<span class="wpwca-status-pill wpwca-pill-error">Error</span>
											<?php else : ?>
												<span class="wpwca-status-pill wpwca-pill-info"><?php echo esc_html( ucfirst( $log['status'] ) ); ?></span>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( $log['provider'] ? $log['provider'] : '—' ); ?></td>
										<td>
											<strong><?php echo esc_html( $log['title'] ); ?></strong>
											<?php if ( ! empty( $log['post_id'] ) ) : ?>
												<br><a href="<?php echo esc_url( get_edit_post_link( $log['post_id'] ) ); ?>" target="_blank">Post #<?php echo esc_html( $log['post_id'] ); ?></a>
											<?php endif; ?>
										</td>
										<td><pre class="wpwca-log-pre"><?php echo esc_html( $log['message'] ); ?></pre></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>

		</div>
		<?php
	}
}
