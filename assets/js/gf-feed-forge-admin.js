jQuery(function($) {
	$('#doaction, #doaction2').click(function () {
		const action = $(this).siblings('select').val();
		if (action == -1) {
			return;
		}

		const defaultModalOptions = '';
		const entryIds = getLeadIds();

		if (entryIds.length != 0 && action == 'process_feeds') {
			resetProcessFeedsUI();
			tb_show(
				GFFF_ADMIN.modalHeader,
				'#TB_inline?width=350&amp;inlineId=feeds_modal_container',
				'',
			);
			return false;
		}
	});

	$('input[name="feed_process"]').on('click', function () {
		const selectedFeeds = new Array();

		$('.gform_feeds:checked').each(function () {
			selectedFeeds.push($(this).val());
		});

		const leadIds = getLeadIds();

		if (selectedFeeds.length <= 0) {
			displayMessage(
				GFFF_ADMIN.noSelectedFeedMsg,
				'error',
				'#feeds_container',
			);
			return;
		}

		$('#feeds_please_wait_container').fadeIn();

		$.post(
			ajaxurl,
			{
				action: 'gf_process_feeds',
				nonce: GFFF_ADMIN.nonce,
				feeds: $.toJSON(selectedFeeds),
				leadIds: leadIds,
				formId: GFFF_ADMIN.formId,
			},
			function (response) {
				$('#feeds_please_wait_container').hide();

				if (response.success) {
					const count =
						leadIds == 0
							? gformVars.countAllEntries
							: leadIds.length;
					displayMessage(
						GFFF_ADMIN.successMsg.replace(
							'%s',
							`${count}  ${getPlural(count, GFFF_ADMIN.entryString, GFFF_ADMIN.entriesString)}`,
						),
						'success',
						'#entry_list_form',
					);
					closeModal(true);
				} else {
					displayMessage(response.data, 'error', '#feeds_container');
				}
			},
		);
	});

	function resetProcessFeedsUI() {
		$('.gform_feeds').prop('checked', false);
		$('#feeds_container .message').hide();
	}
});
