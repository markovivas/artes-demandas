(function ($) {
	"use strict";

	$(".arte-kanban-list").sortable({
		connectWith: ".arte-kanban-list",
		placeholder: "arte-kanban-placeholder",
		forcePlaceholderSize: true,
		stop: function (event, ui) {
			const card = ui.item;
			const postId = card.data("post-id");
			const status = card.closest(".arte-kanban-column").data("status");

			$.post(arteKanban.ajaxUrl, {
				action: "arte_update_status",
				nonce: arteKanban.nonce,
				postId: postId,
				status: status
			}).fail(function () {
				window.location.reload();
			});
		}
	}).disableSelection();
})(jQuery);
