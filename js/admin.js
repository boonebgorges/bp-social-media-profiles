jQuery(document).ready(function($) {
	$('#bp_smp_site').change(function(){
		var site = $(this).val();
		var updiv = $('#url-pattern');
		var addiv = $('#admin-desc');

		if ( '' == site ) {
			$('#bp_smp_url_pattern').val('');
			$('#admin-desc p').html('');

			if ( 'none' != $(updiv).css('display') ) {
				$(updiv).fadeOut('fast');
			}

			if ( 'none' != $(addiv).css('display') ) {
				$(addiv).fadeOut('fast');
			}
		} else {
			$.post( ajaxurl, {
				action: 'get_url_pattern',
				'site': site
			},
			function(response)
			{
				data = JSON.parse(response);

				$('#bp_smp_url_pattern').val(data.url_pattern);
				$('#admin-desc p').html(data.admin_desc);

				/* Show/hide the URL pattern div */
				if ( '' != data.url_pattern && 'none' == $(updiv).css('display') ) {
					$(updiv).fadeIn('fast');
				} else if ( '' == data.url_pattern && 'none' != $(updiv).css('display') ) {
					$(updiv).fadeOut('fast');
				}

				/* Show/hide the admin_desc div */
				if ( '' != data.admin_desc && 'none' == $(addiv).css('display') ) {
					$(addiv).fadeIn('fast');
				} else if ( '' == data.admin_desc && 'none' != $(addiv).css('display') ) {
					$(addiv).fadeOut('fast');
				}

			});
		}
	});
},(jQuery));