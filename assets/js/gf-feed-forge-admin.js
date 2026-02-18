jQuery(function($) {
	var gfffAbortQueue = false;
	$(document).on('click', '#gfff-abort-queue', function(e) {
		e.preventDefault();
		gfffAbortQueue = true;
		displayMessage(GFFF_ADMIN.abortSuccessMsg, 'error', '#entry_list_form');

		$.post(ajaxurl, {
			action: 'gf_process_feeds_cancel',
			gf_process_feeds: GFFF_ADMIN.nonce,
		}).done(function(response) {
			if (response.success) {
				$('#entry_list_form alert').closest('.alert').hide();
				gfffAbortQueue = true;
				$('input[name="feed_process"]').prop('disabled', false);
				closeModal(false);
				location.reload();
			}
		});

		// Hide the abort button after click
		$(this).hide();
	});

	$('#doaction, #doaction2').click(function() {
		var action = $(this).siblings('select').val();
		if (action == -1) {
			return;
		}

		var defaultModalOptions = '';
		var entryIds = getLeadIds();

		if (entryIds.length != 0 && action == 'process_feeds') {
			resetProcessFeedsUI();
			tb_show(
				"<div class='tb-title'>" +
				"<div class='tb-title__logo'></div>" +
				"<div class='tb-title__text'>" +
				"<div class='tb-title__main'>" + GFFF_ADMIN.modalHeader + "</div>" +
				"<div class='tb-title__sub'>" + GFFF_ADMIN.modalDescription + "</div>" +
				"</div>" +
				"</div>",
				'#TB_inline?width=600&height=455&inlineId=feeds_modal_container',
				''
			);

			$('#gfff-reprocess-feeds-container').show();
			$('#gfff-progress-bar').hide();
			$('#gfff-progress-bar span').width('0');

			jQuery('#TB_ajaxContent').css({'overflow': 'hidden', 'position': 'static'});
			return false;
		}
	});

	$('input[name="feed_process"]').on('click', function() {
		var selectedFeeds = [];
		gfffAbortQueue = false;

		$('.gform_feeds:checked').not('#reprocess_feeds').each(function() {
			selectedFeeds.push($(this).val());
		});

		var leadIds = getLeadIds();

		if (selectedFeeds.length <= 0) {
			displayMessage(
				GFFF_ADMIN.noSelectedFeedMsg,
				'error',
				'#feeds_container'
			);
			return;
		}

		$(this).prop('disabled', true);
		$('#gfff-reprocess-feeds-container').hide();
		$('#gfff-progress-bar').show();

		gfffBatch($.toJSON(selectedFeeds), leadIds, 1000, 1, 0, null);
	});

	function gfffBatch(feeds, leadIds, size, page, count, total) {
		if (gfffAbortQueue) {
			$('input[name="feed_process"]').prop('disabled', false);
			closeModal(false);
			return;
		}

		$.post(
			ajaxurl,
			{
				action: 'gf_process_feeds',
				gf_process_feeds: GFFF_ADMIN.nonce,
				processor_nonce: GFFF_ADMIN.processor_nonce, // Include processor-specific nonce in request for verification on GF side.
				formId: GFFF_ADMIN.formId,
				reprocess_feeds: $('#reprocess_feeds').is(':checked'),
				feeds,
				leadIds,
				size,
				page,
				count,
				total,
			},
			function(response) {
				if (gfffAbortQueue || (response.success && response.data.aborted)) {
					$('input[name="feed_process"]').prop('disabled', false);
					closeModal(false);
					return;
				}

				if (gfffAbortQueue) {
					$('input[name="feed_process"]').prop('disabled', false);
					closeModal(false);
					return;
				}
				if (response.success) {
					if (
						typeof response.data == 'string' &&
						response.data == 'done'
					) {
						var count =
							leadIds == 0
								? gformVars.countAllEntries
								: leadIds.length;
						const abortBtn = `<a href="#" id="gfff-abort-queue" style="color:#a94442;float:right;margin-left:20px;">${GFFF_ADMIN.abortQueueMsg}</a>`;
						displayMessage(
							GFFF_ADMIN.successMsg.replace(
								'%s',
								`${count}  ${getPlural(count, GFFF_ADMIN.entryString, GFFF_ADMIN.entriesString)}`,
							) + abortBtn,
							'success',
							'#entry_list_form',
						);
						$('input[name="feed_process"]').prop('disabled', false);
						closeModal(true);
					} else {
						$('#gfff-progress-bar span').width(
							`${(response.data.count / response.data.total) * 100}%`,
						);
						gfffBatch(
							feeds,
							leadIds,
							response.data.size,
							response.data.page,
							response.data.count,
							response.data.total,
						);
					}
				} else {
					$('input[name="feed_process"]').prop('disabled', false);
					closeModal(false);
					displayMessage(
						response.data.message,
						'error',
						'#entry_list_form',
					);
				}
			},
		).fail(function(response) {
			$('input[name="feed_process"]').prop('disabled', false);
			closeModal(false);
			displayMessage(
				GFFF_ADMIN.genericErrorMsg,
				'error',
				'#entry_list_form',
			);
		});
	}

	function resetProcessFeedsUI() {
		$('.gform_feeds').prop('checked', false);
		$('#feeds_container .message').hide();
	}
});
