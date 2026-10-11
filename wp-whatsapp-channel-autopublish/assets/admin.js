/**
 * Admin JavaScript for WhatsApp Channel AutoPublish
 */

(function($) {
	'use strict';

	$(document).ready(function() {

		// 1. Toggle Provider Sections on Settings Page
		$('input[name="wpwca_settings[provider_mode]"]').on('change', function() {
			var mode = $(this).val();
			if (mode === 'meta_cloud') {
				$('#wpwca-channel-config').slideUp(200);
				$('#wpwca-meta-config').slideDown(200);
			} else {
				$('#wpwca-meta-config').slideUp(200);
				$('#wpwca-channel-config').slideDown(200);
			}
		});

		// 2. Toggle Meta Template settings
		$('#meta_message_mode').on('change', function() {
			if ($(this).val() === 'template') {
				$('.wpwca-row-meta-template').show();
			} else {
				$('.wpwca-row-meta-template').hide();
			}
		});

		// 3. Insert Token Tags into Message Template textarea
		$('.wpwca-insert-tag').on('click', function(e) {
			e.preventDefault();
			var tag = $(this).data('tag');
			var textarea = document.getElementById('message_template');
			if (!textarea) return;

			var start = textarea.selectionStart;
			var end = textarea.selectionEnd;
			var text = textarea.value;

			textarea.value = text.substring(0, start) + tag + text.substring(end);
			textarea.focus();
			textarea.selectionStart = textarea.selectionEnd = start + tag.length;
		});

		// 4. Test Connection Button (Settings Page)
		$('#wpwca-test-connection-btn').on('click', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var $result = $('#wpwca-test-result');

			$btn.prop('disabled', true).addClass('updating-message');
			$result.show().html('<span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span> ' + wpwcaAdmin.strings.testing);

			$.post(wpwcaAdmin.ajaxUrl, {
				action: 'wpwca_test_connection',
				nonce: wpwcaAdmin.nonce
			}, function(response) {
				$btn.prop('disabled', false).removeClass('updating-message');
				if (response.success) {
					$result.html('<div class="notice notice-success inline" style="margin:8px 0; padding:8px 12px;"><p><strong>Success!</strong> ' + response.data.message + '</p></div>');
				} else {
					$result.html('<div class="notice notice-error inline" style="margin:8px 0; padding:8px 12px;"><p><strong>Error:</strong> ' + response.data.message + '</p></div>');
				}
			}).fail(function() {
				$btn.prop('disabled', false).removeClass('updating-message');
				$result.html('<div class="notice notice-error inline" style="margin:8px 0; padding:8px 12px;"><p><strong>Network Error:</strong> Failed to reach server.</p></div>');
			});
		});

		// 5. Manual Broadcast Button (Post Edit Screen Meta Box)
		$('.wpwca-manual-broadcast-btn').on('click', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var postId = $btn.data('post-id');
			var $responseBox = $btn.siblings('.wpwca-manual-response');

			$btn.prop('disabled', true);
			$responseBox.show().html('<span class="spinner is-active" style="float:none; margin:0 4px 0 0;"></span> ' + wpwcaAdmin.strings.broadcasting);

			$.post(wpwcaAdmin.ajaxUrl, {
				action: 'wpwca_manual_broadcast',
				nonce: wpwcaAdmin.nonce,
				post_id: postId
			}, function(response) {
				$btn.prop('disabled', false);
				if (response.success) {
					$responseBox.html('<span style="color:#0f6848;"><strong>✓ Broadcasted!</strong> ' + response.data.message + '</span>');
					var $badge = $('.wpwca-metabox-content .wpwca-badge');
					$badge.removeClass('wpwca-badge-pending wpwca-badge-error').addClass('wpwca-badge-success');
					$badge.html('<span class="dashicons dashicons-yes-alt"></span> Broadcasted: ' + response.data.timestamp);
				} else {
					$responseBox.html('<span style="color:#d63638;"><strong>Error:</strong> ' + response.data.message + '</span>');
				}
			}).fail(function() {
				$btn.prop('disabled', false);
				$responseBox.html('<span style="color:#d63638;"><strong>Failed:</strong> Server connection error.</span>');
			});
		});

		// 6. Clear Logs Button
		$('#wpwca-clear-logs-btn').on('click', function(e) {
			e.preventDefault();
			if (!confirm(wpwcaAdmin.strings.confirmClear)) {
				return;
			}

			var $btn = $(this);
			$btn.prop('disabled', true);

			$.post(wpwcaAdmin.ajaxUrl, {
				action: 'wpwca_clear_logs',
				nonce: wpwcaAdmin.nonce
			}, function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.data.message);
					$btn.prop('disabled', false);
				}
			});
		});

	});

})(jQuery);
