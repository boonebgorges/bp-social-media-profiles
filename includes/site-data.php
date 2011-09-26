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
				'url_pattern'   => 'http://twitter.com/***',
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
	 * CALLBACK FUNCTIONS
	 */

	/**
	 * Callback for Twitter
	 */
	function twitter_cb( $user_data, $field_data ) {
		var_dump( $user_data ); var_dump( $field_data ); die();
	}

	/**
	 * Callback for YouTube
	 */
	function youtube_cb( $user_data, $field_data ) {
		var_dump( $user_data ); var_dump( $field_data ); die();
	}
}