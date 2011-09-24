<?php
/*
Plugin Name: BP Social Media Profiles
Author: Boone B Gorges
Description: Renders certain xprofile fields as social media icons
Version: 1.0
*/

define( 'BP_SMP_VERSION', '1.0' );
define( 'BP_SMP_PLUGIN_DIR', trailingslashit( dirname( __FILE__ ) ) );
define( 'BP_SMP_PLUGIN_URL', trailingslashit( plugins_url( array_pop( explode( '/', dirname( __FILE__ ) ) ) ) ) ); // I am a tricky guy

function bp_smp_loader() {
	require( BP_SMP_PLUGIN_DIR . 'bp-social-media-profiles.php' );
}
add_action( 'bp_include', 'bp_smp_loader' );

?>
