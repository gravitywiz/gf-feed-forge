jQuery(function($) {
	$('#doaction, #doaction2').click(function () {
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
			return false;
		}
	});

	$('input[name="feed_process"]').on('click', function () {
		var selectedFeeds = [];

		$('.gform_feeds:checked').each(function () {
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

		$('#feeds_please_wait_container').fadeIn();

		$.post(
			ajaxurl,
			{
				action: 'gf_process_feeds',
				gf_process_feeds: GFFF_ADMIN.nonce,
				feeds: $.toJSON(selectedFeeds),
				leadIds: leadIds,
				formId: GFFF_ADMIN.formId,
			},
			function (response) {
				$('#feeds_please_wait_container').hide();

				if (response.success) {
					var count =
						leadIds == 0
							? gformVars.countAllEntries
							: leadIds.length;
					displayMessage(
						GFFF_ADMIN.successMsg.replace(
							'%s',
							count + "  " + getPlural(count, GFFF_ADMIN.entryString, GFFF_ADMIN.entriesString)
						),
						'success',
						'#entry_list_form'
					);
					closeModal(true);
				} else {
					closeModal(false);
					displayMessage(response.data.message, 'error', '#entry_list_form');
				}
			}
		).fail(function (response) {
			$('#feeds_please_wait_container').hide();
			closeModal(false);
			displayMessage(GFFF_ADMIN.genericErrorMsg, 'error', '#entry_list_form');
		});
	});

	function resetProcessFeedsUI() {
		$('.gform_feeds').prop('checked', false);
		$('#feeds_container .message').hide();
	}
});
