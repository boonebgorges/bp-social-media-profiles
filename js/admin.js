jQuery(document).ready(function($) {
	$('#bp_smp_site').change(function(){
		var site = $(this).val();
		var updiv = $('#url-pattern');

		if ( '' == site ) {
			$('#bp_smp_url_pattern').val('');

			if ( 'none' != $(updiv).css('display') ) {
				$('#url-pattern').fadeOut('fast');
			}
		} else {
			$.post( ajaxurl, {
				action: 'get_url_pattern',
				'site': site
			},
			function(response)
			{
				$('#bp_smp_url_pattern').val(response);

				if ( '' != response && 'none' == $(updiv).css('display') ) {
					$('#url-pattern').fadeIn('fast');
				}

				if ( '' == response && 'none' != $(updiv).css('display') ) {
					$('#url-pattern').fadeOut('fast');
				}
			});
		}
	});
},(jQuery));