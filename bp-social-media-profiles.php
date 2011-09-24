<?php

class BP_Social_Media_Profiles extends BP_Component {
	/**
	 * The available SMP site data
	 */
	var $smp_site_data;

	/**
	 * The ID of the current field, if we're looking at a single field in the admin
	 */
	var $field_id;

	/**
	 * The field data object, if we're looking at a single field in the admin
	 */
	var $field;

	/**
	 * The social media profile metadata for this single field, if it exists
	 */
	var $field_smp_data;

	function __construct() {
		parent::start(
			'bp_smp',
			__( 'BuddyPress Social Media Profiles', 'bp-smp' ),
			BP_SMP_PLUGIN_DIR
		);

		$this->setup_smp_site_data();

		$this->setup_hooks();
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
				'name'		=> __( 'Facebook', 'bp-smp' )
			),
			'youtube' => array(
				'name'		=> __( 'YouTube', 'bp-smp' ),
				'url_pattern'	=> 'http://youtube.com/***',
				'callback'	=> array( &$this, 'youtube_cb' ),
				'admin_desc'	=> __( 'Accepts a YouTube user name, or the full URL to a YouTube user page', 'bp-smp' )
			),
		) );

		// Todo: allow merges from saved custom sites
		$this->smp_site_data = $defaults;
	}

	function setup_hooks() {
		// Get the initial field data when on the admin
		add_action( 'admin_init', array( &$this, 'setup_single_field' ) );

		// Display the admin markup
		add_action( 'xprofile_field_additional_options', array( &$this, 'add_admin_field' ) );

		// Catch saved profile field data on Dashboard > Profile Fields
		add_action( 'xprofile_fields_saved_field', array( &$this, 'save_admin_field' ) );

		// Admin scripts and styles
		add_action( 'admin_print_styles', array( &$this, 'admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'admin_scripts' ) );

		// Get URL pattern ajax hook
		add_action( 'wp_ajax_get_url_pattern', array( &$this, 'ajax_get_url_pattern' ) );
	}

	function setup_single_field() {
		if ( isset( $_GET['page'] ) && 'bp-profile-setup' == $_GET['page'] ) {
			// Get the field id out of the URL
			$this->field_id = isset( $_GET['field_id'] ) ? (int)$_GET['field_id'] : NULL;

			if ( !$this->field_id ) {
				return;
			}

			// Get the field data from the database
			$this->field = new BP_XProfile_Field( $this->field_id );

			if ( empty( $this->field ) ) {
				return;
			}

			// See if this field has bp_smp data attached yet
			$this->field_smp_data = bp_xprofile_get_meta( $this->field_id, 'field', 'bp_smp_data' );
		}
	}

	function save_admin_field() {
		if ( isset( $_POST['bp_smp'] ) ) {
			$new_smp_data = $_POST['bp_smp'];
			bp_xprofile_update_field_meta( $this->field_id, 'bp_smp_data', $new_smp_data );
		}
	}

	function add_admin_field( $field ) {
		$current_site = isset( $this->field_smp_data['site'] ) ? $this->field_smp_data['site'] : '';

		if ( isset( $this->field_smp_data['url_pattern'] ) ) {
			$url_pattern = $this->field_smp_data['url_pattern'];
		} else if ( isset( $this->smp_site_data[$current_site]['url_pattern'] ) ) {
			$url_pattern = $this->smp_site_data[$current_site]['url_pattern'];
		} else {
			$url_pattern = '';
		}

		?>

		<div id="titlediv" class="bp-smp-info">
			<h3><?php _e( 'Social Media Information', 'bp-smp' ) ?></h3>

			<label for="bp_smp[site]">
				<?php _e( 'Site: ', 'bp-smp' ) ?>
			</label>

			<select name="bp_smp[site]" id="bp_smp_site" style="width:30%;">
				<option value="" <?php selected( $current_site, '' ) ?>><?php _e( 'Not a social media field', 'bp-smp' ) ?></option>

				<?php foreach ( $this->smp_site_data as $site_id => $site ) : ?>
					<option value="<?php echo esc_attr( $site_id ) ?>" <?php selected( $current_site, $site_id ) ?>><?php echo esc_html( $site['name'] ) ?></option>
				<?php endforeach ?>
			</select>

			<br />

			<?php if ( $current_site && $url_pattern ) : ?>
				<div id="url-pattern">

					<label for="bp_smp[url_pattern]">
						<?php _e( 'URL Pattern: ', 'bp-smp' ) ?>
					</label>

					<input name="bp_smp[url_pattern]" id="bp_smp_url_pattern" value="<?php echo esc_attr( $url_pattern ) ?>" style="width:30%" />
					<p class="description"><?php __( 'Use three asterisks <strong>***</strong> where you want user input to appear.', 'bp-smp' ) ?>

				</div>
			<?php endif ?>

		</div>

		<?php
	}

	/**
	 * Gets the url pattern based on site name
	 *
	 * @param str $site Site name to be fetched from site info array
	 * @return str URL pattern
	 */
	function get_url_pattern( $site ) {
		if ( isset( $this->smp_site_data[$site]['url_pattern'] ) ) {
			return $this->smp_site_data[$site]['url_pattern'];
		} else {
			return '';
		}
	}

	/**
	 * AJAX handler for url pattern fetcher
	 */
	function ajax_get_url_pattern() {
		$site = isset( $_POST['site'] ) ? $_POST['site'] : '';

		if ( $site ) {
			$url_pattern = $this->get_url_pattern( $site );
		} else {
			// error
		}

		echo $url_pattern;
		die();
	}

	function admin_styles() {
		if ( isset( $_GET['page'] ) && 'bp-profile-setup' == $_GET['page'] ) {
			wp_enqueue_style( 'bp-smp-admin-css', BP_SMP_PLUGIN_URL . 'css/admin.css' );
		}
	}

	function admin_scripts() {
		if ( isset( $_GET['page'] ) && 'bp-profile-setup' == $_GET['page'] ) {
			wp_enqueue_script( 'bp-smp-admin-js', BP_SMP_PLUGIN_URL . 'js/admin.js', array( 'jquery' ) );
		}
	}
}

function bp_smp_load_core_component() {
	global $bp;

	$bp->social_media_profiles = new BP_Social_Media_Profiles;
}
add_action( 'bp_loaded', 'bp_smp_load_core_component' );

?>