(function( $ ) {
	$(document)
		.bind('gform_page_loaded', function( event, form_id, current_page ) {
			jQuery(document).scrollTop(0);
		})
		.bind('gform_post_render', function( event, form_id, current_page ){
			window.dataLayer = window.dataLayer || [];
			jQuery(document).scrollTop(0);
		});
})(jQuery);