<?php

/**
 * Default social media service data and callback functions
 */
class BP_SMP_Site_Data {
	var $sites;

	function __construct() {
		$this->setup_smp_site_data();
	}

	/**
	 * Creates the default data for SMP fields
	 */
	function setup_smp_site_data() {
		$defaults = apply_filters( 'bp_smp_default_site_data', array(
			'twitter' => array(
				'name' 		=> __( 'Twitter', 'bp-smp' ),
				'url_pattern'   => 'http://twitter.com/***/',
				'callback'	=> array( &$this, 'twitter_cb' ),
				'admin_desc'	=> __( 'Accepts a Twitter handle with or without the @ sign, or the full URL to a Twitter profile', 'bp-smp' )
			),
			'facebook' => array(
				'name'		=> __( 'Facebook', 'bp-smp' ),
				'admin_desc'	=> __( 'Accepts the URL to a Facebook user profile', 'bp-smp' )
			),
			'youtube' => array(
				'name'		=> __( 'YouTube', 'bp-smp' ),
				'url_pattern'	=> 'http://youtube.com/***',
				'callback'	=> array( &$this, 'youtube_cb' ),
				'admin_desc'	=> __( 'Accepts a YouTube user name, or the full URL to a YouTube user page', 'bp-smp' )
			),
		) );

		// Todo: allow merges from saved custom sites
		$this->sites = $defaults;
	}

	/**
	 * HELPER FUNCTIONS
	 *
	 * These functions do some of the heavy lifting.
	 */

	/**
	 * Is this a complete URL? Generic check (non-site-specific)
	 *
	 * Borrowed from http://phpcentral.com/208-url-validation-in-php.html
	 *
	 * @param str $url The string being checked
	 * @return bool True if it's a URL
	 */
	function validate_url( $url = '' ) {
		return preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $url);
	}

	function get_username_using_url_pattern( $saved_value = '', $url_pattern = '' ) {
		if ( !$saved_value || !$url_pattern ) {
			return false;
		}

		$username = false;

		$wcpos = strpos( $url_pattern, '***' );
		if ( false !== $wcpos ) {
			$url_pattern = trailingslashit( $url_pattern );
			$maybe_url   = trailingslashit( $saved_value );

			// Standardize http v https
			$url_pattern = str_replace( 'https:', 'http:', $url_pattern );
			$maybe_url   = str_replace( 'https:', 'http:', $maybe_url );

			$pattern_before = substr( $url_pattern, 0, $wcpos );
			$pattern_after  = substr( $url_pattern, $wcpos + 3 );

			// Try to strip out the 'before' and 'after' parts of the URL from the
			// user-provided data, and see if you're left with anything.
			if ( false !== strpos( $maybe_url, $pattern_before ) && ( !$pattern_after || false !== strpos( $maybe_url, $pattern_after ) ) ) {
				$username = str_replace( $pattern_before, '', $maybe_url );

				// $pattern_after is trickier. It might be something like a slash,
				// in which case we should only replace the final instance.
				$pa_pos = strrpos( $username, $pattern_after );

				if ( false !== $pa_pos ) {
					$username = substr_replace( $username, '', $pa_pos, strlen( $pattern_after ) );
				}
			}
		}

		return apply_filters( 'bp_smp_get_username_using_url_pattern', $username, $saved_value, $url_pattern );
	}

	/**
	 * Concatenate a URL out of a username value + a URL pattern
	 */
	function get_url_using_username( $username, $url_pattern ) {
		return apply_filters( 'bp_smp_get_url_using_username', str_replace( '***', $username, $url_pattern ), $username, $url_pattern );
	}

	function get_icon_url_from_site_name( $site_name = '' ) {
		return apply_filters( 'bp_smp_get_icon_url_from_site_name', BP_SMP_PLUGIN_URL . 'images/icons/' . $site_name . '.png' );
	}

	/**
	 * CALLBACK FUNCTIONS
	 *
	 * BP Social Media Profiles callback functions are used to save custom data for each
	 * social media field in your BuddyPress profile.
	 *
	 * Callbacks receieve two arguments: $user_data (a BuddyPress BP_XProfile_ProfileData
	 * object) and $field_data (an array containing information about the field, as saved by
	 * the site admin - usually, 'site' (a unique string identifying the site associated with
	 * the field) and 'url_pattern' (a string with the pattern to be used for creating URLs
	 * out of usernames, with '***' as the username wildcard).
	 *
	 * Callbacks should return an array with the following structure:
	 *   array(
	 *     'url'   => $url, // The URL of the SM profile. Used to create links
	 *     'icon'  => $icon // URL path to the icon displayed in the profile
	 *     'text'  => $text, // The text of the profile link
	 *     'title' => $title, // The 'title' attribute of the link
	 *     'html'  => $html // The pre-rendered HTML for this link
	 *   );
	 *
	 * These return values are then processed by BP-SMP, saved to the database, and then pulled
	 * up during the rendering of profiles.
	 *
	 * Not all of these values are required; they are designed to give you some flexibility with
	 * respect to displaying the social media links. Some guidelines:
	 *   - If you provide 'html', no other field is required. The plugin will not attempt to
	 *     auto-generate HTML, but will simply output this value. Use this if you need the field
	 *     to display in a highly-customized way. If you want the plugin to generate the HTML
	 *     for you, your callback should not provide a value for 'html'.
	 *   - If you provide a 'url' value, the social media icon/text will be displayed as a link
	 *   - If you provide a 'text' value, the output will contain text, eg 'My Twitter Profile'
	 *   - If you provide an 'icon' value, the output will contain an image, eg a Twitter icon
	 *   - If you provide a 'title' value, it will be used for the 'title' attribute of the
	 *     link. Note that if your callback does not provide a 'url', 'title' will do nothing.
	 *     If you don't provide a 'title', it will fall back to 'text' if available, then 'url'.
	 */

	/**
	 * Callback for Twitter
	 */
	function twitter_cb( $user_data, $field_data ) {
		$saved_value = $user_data->value;
		$url_pattern = $field_data['url_pattern'];

		// First, assume the user-provided value is a URL, and try to get a username
		if ( $username = $this->get_username_using_url_pattern( $saved_value, $url_pattern ) ) {
			// Account for hashbangs
			$url 	  = $saved_value;
			$username = str_replace( '#!/', '', $username );
		} else {
			// Entered value is not a URL, so it must be a username
			$url   	  = $this->get_url_using_username( $saved_value, $url_pattern );
			$username = $saved_value;
		}

		$return = array(
			'url' 	=> $url,
			'icon'	=> $this->get_icon_url_from_site_name( $field_data['site'] ),
			'text'	=> $username,
			'title'	=> sprintf( __( '%s on Twitter', 'bp-smp' ), $username ),
		);

		return apply_filters( 'bp_smp_twitter_cp', $return, $user_data, $field_data );
	}

	/**
	 * Callback for YouTube
	 */
	function youtube_cb( $user_data, $field_data ) {
		var_dump( $user_data ); var_dump( $field_data ); die();
	}
}