/**
 * Gutenberg Block Editor Sidebar Panel for WhatsApp AutoPublish
 */

(function(wp) {
	'use strict';

	if (!wp || !wp.plugins || !wp.editPost || !wp.element || !wp.components || !wp.data) {
		return;
	}

	var registerPlugin             = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var el                         = wp.element.createElement;
	var useState                   = wp.element.useState;
	var useSelect                  = wp.data.useSelect;
	var useDispatch                 = wp.data.useDispatch;
	var CheckboxControl            = wp.components.CheckboxControl;
	var Button                     = wp.components.Button;
	var Spinner                    = wp.components.Spinner;

	function WhatsAppDocumentPanel() {
		// Get current post meta and post ID from Gutenberg data store
		var postMeta = useSelect(function(select) {
			return select('core/editor').getEditedPostAttribute('meta') || {};
		});

		var postId = useSelect(function(select) {
			return select('core/editor').getCurrentPostId();
		});

		var editPost = useDispatch('core/editor').editPost;

		var broadcastEnabled = postMeta._wpwca_enable_broadcast !== 'no';
		var broadcastedAt    = postMeta._wpwca_broadcasted || '';
		var lastStatus       = postMeta._wpwca_last_status || '';
		var lastError        = postMeta._wpwca_last_error || '';

		var broadcastState = useState(false);
		var isBroadcasting = broadcastState[0];
		var setIsBroadcasting = broadcastState[1];

		var feedbackState = useState(null);
		var feedback      = feedbackState[0];
		var setFeedback   = feedbackState[1];

		function onToggleBroadcast(checked) {
			editPost({
				meta: {
					_wpwca_enable_broadcast: checked ? 'yes' : 'no'
				}
			});
		}

		function onManualBroadcast() {
			if (!postId) {
				setFeedback({ type: 'error', text: 'Please save draft before broadcasting.' });
				return;
			}

			setIsBroadcasting(true);
			setFeedback(null);

			var ajaxUrl = (window.wpwcaAdmin && window.wpwcaAdmin.ajaxUrl) ? window.wpwcaAdmin.ajaxUrl : '/wp-admin/admin-ajax.php';
			var nonce   = (window.wpwcaAdmin && window.wpwcaAdmin.nonce) ? window.wpwcaAdmin.nonce : '';

			jQuery.post(ajaxUrl, {
				action:  'wpwca_manual_broadcast',
				nonce:   nonce,
				post_id: postId
			}, function(response) {
				setIsBroadcasting(false);
				if (response.success) {
					setFeedback({ type: 'success', text: '✓ ' + response.data.message });
					editPost({
						meta: {
							_wpwca_broadcasted:   response.data.timestamp || new Date().toISOString(),
							_wpwca_last_status:   'success',
							_wpwca_last_error:    ''
						}
					});
				} else {
					setFeedback({ type: 'error', text: '✗ ' + (response.data && response.data.message ? response.data.message : 'Error') });
				}
			}).fail(function() {
				setIsBroadcasting(false);
				setFeedback({ type: 'error', text: '✗ Server network error' });
			});
		}

		// Status Badge element
		var statusBadge;
		if (broadcastedAt) {
			statusBadge = el('div', {
				style: {
					padding: '6px 10px',
					background: '#e7f7ee',
					color: '#0f6848',
					borderRadius: '4px',
					fontSize: '12px',
					fontWeight: '500',
					marginBottom: '12px',
					display: 'flex',
					alignItems: 'center',
					gap: '5px'
				}
			}, '✓ Broadcasted: ' + broadcastedAt);
		} else if (lastStatus === 'failed') {
			statusBadge = el('div', {
				style: {
					padding: '6px 10px',
					background: '#fdf2f2',
					color: '#d63638',
					borderRadius: '4px',
					fontSize: '12px',
					fontWeight: '500',
					marginBottom: '12px'
				}
			}, [
				el('div', { key: 'err-title' }, '⚠ Broadcast Failed'),
				lastError ? el('div', { key: 'err-msg', style: { fontSize: '11px', marginTop: '3px' } }, lastError) : null
			]);
		} else {
			statusBadge = el('div', {
				style: {
					padding: '6px 10px',
					background: '#f0f0f1',
					color: '#646970',
					borderRadius: '4px',
					fontSize: '12px',
					fontWeight: '500',
					marginBottom: '12px'
				}
			}, '○ Not yet broadcasted');
		}

		// Feedback message
		var feedbackElem = null;
		if (feedback) {
			feedbackElem = el('div', {
				style: {
					marginTop: '8px',
					padding: '6px 8px',
					fontSize: '12px',
					borderRadius: '4px',
					background: feedback.type === 'success' ? '#e7f7ee' : '#fdf2f2',
					color: feedback.type === 'success' ? '#0f6848' : '#d63638'
				}
			}, feedback.text);
		}

		return el(PluginDocumentSettingPanel, {
			name: 'wpwca-post-panel',
			title: 'WhatsApp Broadcast',
			icon: 'megaphone'
		}, [
			el(CheckboxControl, {
				key: 'checkbox',
				label: 'Broadcast to WhatsApp on publish',
				checked: broadcastEnabled,
				onChange: onToggleBroadcast
			}),
			el('div', { key: 'status', style: { marginTop: '8px' } }, statusBadge),
			el('div', { key: 'actions', style: { marginTop: '12px', paddingTop: '10px', borderTop: '1px solid #e0e0e0' } }, [
				el(Button, {
					key: 'broadcast-btn',
					variant: 'secondary',
					isSmall: true,
					disabled: isBroadcasting,
					onClick: onManualBroadcast,
					style: { width: '100%', justifyContent: 'center' }
				}, isBroadcasting ? [el(Spinner, { key: 'spin' }), ' Broadcasting...'] : 'Broadcast to WhatsApp Now'),
				feedbackElem
			])
		]);
	}

	function initPlugin() {
		registerPlugin('wpwca-gutenberg-panel', {
			render: WhatsAppDocumentPanel,
			icon: 'megaphone'
		});
	}

	if (wp.domReady) {
		wp.domReady(initPlugin);
	} else {
		initPlugin();
	}

})(window.wp);
