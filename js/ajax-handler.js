$('body').on( 'submit', '.html-importer-progress', function(e) {
	e.preventDefault();

	var data = $(this).serialize();

	$(this).append( '<span class="spinner is-active"></span><div class="import-progress"><div></div></div>' );

	// start the process
	self.process_step( 1, data, self );

});

process_step : function( step, data, self ) {

	$.ajax({
		type: 'POST',
		url: ajaxurl,
		data: {
			form: data,
			action: 'html_import_do_ajax_import',
			step: step,
		//	security: $( '#html-import-ajax-nonce' ).val()
		},
		dataType: 'json',
		success: function( response ) {
			if( 'done' == response.step ) {

				var progress_form = $('.html-importer-progress');

				progress_form.find('.spinner').remove();
				progress_form.find('.import-progress').remove();

				window.location = response.url;

			} else {

				$('.import-progress div').animate({
					width: response.percentage + '%',
				}, 50, function() {
					// Animation complete.
				});
				self.process_step( parseInt( response.step ), data, self );
			}

		}
	}).fail(function (response) {
		if ( window.console && window.console.log ) {
			console.log( response );
		}
	});

}