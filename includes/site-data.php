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
	 *
	 * @todo Order?
	 */
	function setup_smp_site_data() {
		$defaults = apply_filters( 'bp_smp_default_site_data', array(
			'twitter' => array(
				'name' 		=> __( 'Twitter', 'bp-smp' ),
				'url_pattern'   => 'http://twitter.com/***/',
				'callback'	=> array( &$this, 'twitter_cb' ),
				'admin_desc'	=> __( 'Accepts a Twitter handle with or without the @ sign, or the full URL to a Twitter profile', 'bp-smp' )
			),
			'googleplus' => array(
				'name' 		=> __( 'Google Plus', 'bp-smp' ),
				'url_pattern'   => 'http://plus.google.com/+***/',
				'callback'	=> array( &$this, 'google_plus_cb' ),
				'admin_desc'	=> __( 'Accepts a Google Plus handle with or without the + sign, or the full URL to a Google Plus profile', 'bp-smp' )
			),
			'facebook' => array(
				'name'		=> __( 'Facebook', 'bp-smp' ),
				'admin_desc'	=> __( 'Accepts the URL to a Facebook user profile', 'bp-smp' ),
				'callback'	=> array( &$this, 'facebook_cb' )
			),
			'vimeo' => array(
				'name'		=> __( 'Vimeo', 'bp-smp' ),
				'admin_desc'	=> __( 'Accepts the URL to a Vimeo user profile', 'bp-smp' ),
				'callback'	=> array( &$this, 'vimeo_cb' )
			),
			'academia' => array(
				'name'		=> __( 'Academia.edu', 'bp-smp' ),
				'admin_desc'	=> __( 'Accepts the URL to an Academia.edu user profile', 'bp-smp' ),
				'callback'	=> array( &$this, 'academia_cb' )
			),
			'github' => array(
				'name'		=> __( 'Github', 'bp-smp' ),
				'url_pattern'	=> 'http://github.com/***',
				'callback'	=> array( &$this, 'github_cb' ),
				'admin_desc'	=> __( 'Accepts a Github user name, or the full URL to a Github user profile', 'bp-smp' )
			),
			'youtube' => array(
				'name'		=> __( 'YouTube', 'bp-smp' ),
				'url_pattern'	=> 'http://youtube.com/user/***',
				'callback'	=> array( &$this, 'youtube_cb' ),
				'admin_desc'	=> __( 'Accepts a YouTube user name, or the full URL to a YouTube user page', 'bp-smp' )
			),
			'linkedin' => array(
				'name'		=>  __( 'LinkedIn', 'bp-smp' ),
				'url_pattern'	=> 'http://www.linkedin.com/in/***/',
				'callback'	=> array( &$this, 'linkedin_cb' ),
				'admin_desc'	=> __( 'Accepts a LinkedIn profile URL, or a username that can be translated into a custom profile URL (such as http://www.linkedin.com/in/username from "username")', 'bp-smp' )
			),
			'delicious' => array(
				'name'		=> __( 'Delicious', 'bp-smp' ),
				'url_pattern'	=> 'http://delicious.com/***/',
				'callback'	=> array( &$this, 'delicious_cb' ),
				'admin_desc'	=> __( 'Accepts a Delicious profile URL, or a username', 'bp-smp' )
			),
			'flickr' => array(
				'name'		=> __( 'Flickr', 'bp-smp' ),
				'url_pattern'	=> 'http://www.flickr.com/photos/***',
				'callback'	=> array( &$this, 'flickr_cb' ),
				'admin_desc'	=> __( 'Accepts a Flickr username, or the full URL path to a Flickr user page.', 'bp-smp' )
			),
			'pinterest' => array(
				'name'		=> __( 'Pinterest', 'bp-smp' ),
				'url_pattern'	=> 'http://www.pinterest.com/***',
				'callback'	=> array( &$this, 'pinterest_cb' ),
				'admin_desc'	=> __( 'Accepts a Pinterest username, or the full URL path to a Pinterest user page.', 'bp-smp' )
			),
			'lastfm' => array(
				'name'		=> __( 'last.fm', 'bp-smp' ),
				'url_pattern'	=> 'http://www.last.fm/user/***',
				'callback'	=> array( &$this, 'lastfm_cb' ),
				'admin_desc'	=> __( 'Accepts a last.fm username, or the full URL path to a last.fm user page.', 'bp-smp' )
			),
			'instagram' => array(
				'name' 		=> __( 'Instagram', 'bp-smp' ),
				'url_pattern'   => 'http://instagram.com/***/',
				'callback'	=> array( &$this, 'instagram_cb' ),
				'admin_desc'	=> __( 'Accepts an Instagram username, or the full URL to an Instagram profile', 'bp-smp' )
			),
			'tumblr' => array(
				'name'		=> __( 'Tumblr', 'bp-smp' ),
				'url_pattern'	=> 'http://***.tumblr.com',
				'callback'	=> array( &$this, 'tumblr_cb' ),
				'admin_desc'	=> __( 'Accepts a Tumblr username, or the full URL path to a Tumblr blog.', 'bp-smp' )
			),
			'vine' => array(
				'name'		=> __( 'Vine', 'bp-smp' ),
				'url_pattern'	=> 'http://vine.co/***',
				'callback'	=> array( &$this, 'vine_cb' ),
				'admin_desc'	=> __( 'Accepts a Vine username, or the full URL path to a Vine user page.', 'bp-smp' )
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
		// If the user didn't include the http://, but the URL pattern calls for it, let's add it for them
		// if this wasn't a URL and was just a username, that shouldn't affect the result
		if ( 0 !== strpos( $saved_value, 'http' ) && 0 == strpos( $url_pattern, 'http' ) ) {
			$saved_value = 'http://' . $saved_value;
		}

		if ( false !== $wcpos ) {
			$url_pattern = trailingslashit( $url_pattern );
			$maybe_url   = trailingslashit( $saved_value );

			// Standardize http v https
			$url_pattern = str_replace( 'https:', 'http:', $url_pattern );
			$maybe_url   = str_replace( 'https:', 'http:', $maybe_url );

			// Standardize www. v no www.
			$url_pattern = str_replace( 'www.', '', $url_pattern );
			$maybe_url   = str_replace( 'www.', '', $maybe_url );

			// We might have modified the URL pattern temporarily, so we need to get the wcpos again
			$wcpos = strpos( $url_pattern, '***' );

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

				// This way, if the user includes the http:// in their input, we make sure it doesn't get added to the alt text for the image
				if ( 0 !== strpos( $username, 'http:' ) ) {
					$username = str_replace( 'http://', '', $username );
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
		/**
		 * Filters the URL of a site's icon.
		 *
		 * @param string $url       URL of the site icon.
		 * @param string $site_name Name of the site (eg 'twitter').
		 */
		return apply_filters( 'bp_smp_get_icon_url_from_site_name', BP_SMP_PLUGIN_URL . 'images/icons/' . $site_name . '.png', $site_name );
	}

	function standard_data_with_url_callback( $site, $saved_value, $url_pattern ) {
		// First, assume the user-provided value is a URL, and try to get a username
		if ( $username = $this->get_username_using_url_pattern( $saved_value, $url_pattern ) ) {
			$url 	  = $saved_value;
			if ( false === strpos( $url, 'http' ) ) {
				$url = 'http://' . $url;
			}
		} else {
			// Entered value is not a URL, so it must be a username
			$url   	  = $this->get_url_using_username( $saved_value, $url_pattern );
			$username = $saved_value;
		}

		$return = array(
			'url' 	=> $url,
			'icon'	=> $this->get_icon_url_from_site_name( $site ),
			'text'	=> $username,
			'title'	=> sprintf( __( '%1$s on %2$s', 'bp-smp' ), $username, ucwords( $site ) ),
		);

		return $return;
	}

	function standard_data_without_url_callback( $site, $saved_value ) {
		$return = array(
			'url'	=> $saved_value,
			'icon'	=> $this->get_icon_url_from_site_name( $site ),
			'text'	=> $saved_value,
			'title' => ucwords( $site )
		);

		return $return;
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
	 *
	 * This one is customized a bit, because of issues like @ signs and hashbangs
	 */
	function twitter_cb( $value, $url_pattern ) {
		$saved_value = $value;
		$url_pattern = $url_pattern;

		// First, assume the user-provided value is a URL, and try to get a username
		if ( $username = $this->get_username_using_url_pattern( $saved_value, $url_pattern ) ) {
			$url 	  = $saved_value;

			// Account for hashbangs
			$username = str_replace( '#!/', '', $username );

			// Account for at-signs
			$username = str_replace( '@', '', $username );
		} else {
			// Entered value is not a URL, so it must be a username
			$url   	  = $this->get_url_using_username( $saved_value, $url_pattern );
			$username = $saved_value;
		}

		$return = array(
			'url' 	=> $url,
			'icon'	=> $this->get_icon_url_from_site_name( 'twitter' ),
			'text'	=> $username,
			'title'	=> sprintf( __( '%s on Twitter', 'bp-smp' ), $username ),
		);

		return $return;
	}

	/**
	 * Callback for Google Plus
	 *
	 * This one is customized a bit, because of issues like + signs
	 */
	function google_plus_cb( $value, $url_patteren ) {
		$saved_value = $value;
		$url_pattern = $url_pattern;

		// First, assume the user-provided value is a URL, and try to get a username
		if ( $username = $this->get_username_using_url_pattern( $saved_value, $url_pattern ) ) {
			$url 	  = $saved_value;

			// Account for plus-signs
			$username = str_replace( '+', '', $username );
		} else {
			// Entered value is not a URL, so it must be a username
			$url   	  = $this->get_url_using_username( $saved_value, $url_pattern );
			$username = $saved_value;
		}

		$return = array(
			'url' 	=> $url,
			'icon'	=> $this->get_icon_url_from_site_name( 'google-plus' ),
			'text'	=> $username,
			'title'	=> sprintf( __( '%s on Google Plus', 'bp-smp' ), $username ),
		);

		return $return;
	}

	/**
	 * Facebook
	 */
	function facebook_cb( $value, $url_pattern ) {
		return $this->standard_data_without_url_callback( 'facebook', $value );
	}

	/**
	 * Vimeo
	 */
	function vimeo_cb( $value, $url_pattern ) {
		return $this->standard_data_without_url_callback( 'vimeo', $value );
	}

	/**
	 * Academia.edu
	 */
	function academia_cb( $value, $url_pattern ) {
		return $this->standard_data_without_url_callback( 'academia', $value );
	}

	/**
	 * Github
	 */
	function github_cb( $value, $url_pattern ) {
		$return = $this->standard_data_with_url_callback( 'github', $value, $url_pattern );

		$return['title'] = sprintf( __( '%s\'s Github profile', 'bp-smp' ), $return['text'] );

		return $return;
	}

	/**
	 * Callback for YouTube
	 */
	function youtube_cb( $value, $url_pattern ) {
		$return = $this->standard_data_with_url_callback( 'youtube', $value, $url_pattern );

		$return['title'] = sprintf( __( '%s\'s YouTube channel', 'bp-smp' ), $return['text'] );

		return $return;
	}

	/**
	 * LinkedIn
	 */
	function linkedin_cb( $value, $url_pattern ) {
		return $this->standard_data_with_url_callback( 'linkedin', $value, $url_pattern );
	}

	/**
	 * Delicious
	 */
	function delicious_cb( $value, $url_pattern ) {
		return $this->standard_data_with_url_callback( 'delicious', $value, $url_pattern );
	}

	/**
	 * Flickr
	 */
	function flickr_cb( $value, $url_pattern ) {
		return $this->standard_data_with_url_callback( 'flickr', $value, $url_pattern );
	}

	/**
	 * Pinterest
	 */
	function pinterest_cb( $value, $url_pattern ) {
		return $this->standard_data_with_url_callback( 'pinterest', $value, $url_pattern );
	}

	/**
	 * last.fm
	 */
	function lastfm_cb( $value, $url_pattern ) {
		return $this->standard_data_with_url_callback( 'lastfm', $value, $url_pattern );
	}

	/**
	 * Instagram
	 */
	function instagram_cb( $value, $url_pattern ) {
		return $this->standard_data_with_url_callback( 'instagram', $value, $url_pattern );
	}

	/**
	 * Tumblr
	 */
	function tumblr_cb( $value, $url_pattern ) {
		return $this->standard_data_with_url_callback( 'tumblr', $value, $url_pattern );
	}

	/**
	 * Vine
	 */
	function vine_cb( $value, $url_pattern ) {
		return $this->standard_data_with_url_callback( 'vine', $value, $url_pattern );
	}
}