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

	/**
	 * An array of the fields that have been configured as SM fields in this installation.
	 * @see BP_Social_Media_Profiles::load_fieldmeta()
	 */
	var $fieldmeta = false;

	/**
	 * The displayed user's SM fields
	 */
	var $user_sm_fields;

	var $this_user_data_ids;

	/**
	 * Plugin settings
	 */

	function __construct() {
		parent::start(
			'bp_smp',
			__( 'BuddyPress Social Media Profiles', 'bp-smp' ),
			BP_SMP_PLUGIN_DIR
		);

		$this->setup_hooks();

		if ( is_super_admin() && ( is_admin() || is_network_admin() ) ) {
			include( BP_SMP_PLUGIN_DIR . 'includes/admin.php' );
			$this->admin = new BP_SMP_Admin;
		}
	}

	/**
	 * Setup settings
	 */
	function setup_settings() {
		// Save a query if we can help it
		if ( !bp_is_user() ) {
			return;
		}

		// Pull up the existing values
		$settings = bp_get_option( 'bp_smp_settings' );

		$defaults = array(
			'display' => array( 'inline' ),
			'label'   => __( 'Follow me online: ', 'bp-smp' )
		);

		$this->settings = wp_parse_args( $settings, $defaults );
	}

	/**
	 * Creates the default data for SMP fields
	 */
	function setup_smp_site_data() {
		if ( empty( $this->smp_site_data ) ) {
			include( BP_SMP_PLUGIN_DIR . 'includes/site-data.php' );
			$this->smp_site_data = new BP_SMP_Site_Data;
		}
	}

	function setup_hooks() {
		// Setup plugin settings. Hooked late so that we have access to is_ functions
		add_action( 'bp_init', array( &$this, 'setup_settings' ) );

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

		// When user data is saved, determine and run the necessary callback
		add_action( 'xprofile_data_after_save', array( &$this, 'save_field_data' ) );

		// Remove the social media fields from the loop
		add_action( 'bp_has_profile', array( &$this, 'modify_profile_loop' ) );

		// Display hooks
		add_action( 'bp_profile_header_meta', array( &$this, 'display_header' ) );
	}

	function setup_single_field() {
		if ( isset( $_GET['page'] ) && 'bp-profile-setup' == $_GET['page'] ) {
			// We'll need the site and callback data
			$this->setup_smp_site_data();

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
		// Set up some data for pre-filling the fields
		// The id of the site
		$current_site = isset( $this->field_smp_data['site'] ) ? $this->field_smp_data['site'] : '';

		// The admin description is not editable by the user, so it always comes out of
		// $this->smp_site_data->sites
		$site_admin_desc = !empty( $current_site ) && isset( $this->smp_site_data->sites[$current_site]['admin_desc'] ) ? $this->smp_site_data->sites[$current_site]['admin_desc'] : '';

		// URL patterns can be changed, so we first look to see if anything was saved by
		// the user. If not found, we look in the "canonical" data.
		if ( isset( $this->field_smp_data['url_pattern'] ) ) {
			$url_pattern = $this->field_smp_data['url_pattern'];
		} else if ( isset( $this->smp_site_data->sites[$current_site]['url_pattern'] ) ) {
			$url_pattern = $this->smp_site_data->sites[$current_site]['url_pattern'];
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

				<?php foreach ( $this->smp_site_data->sites as $site_id => $site ) : ?>
					<option value="<?php echo esc_attr( $site_id ) ?>" <?php selected( $current_site, $site_id ) ?>><?php echo esc_html( $site['name'] ) ?></option>
				<?php endforeach ?>
			</select>

			<br />

			<div id="admin-desc"<?php if ( !$site_admin_desc ) : ?> style="display:none;"<?php endif ?>>

				<p class="description"><?php echo esc_html( $site_admin_desc ) ?></p>

			</div>

			<div id="url-pattern"<?php if ( !$url_pattern ) : ?> style="display:none;"<?php endif ?>>

				<label for="bp_smp[url_pattern]">
					<?php _e( 'URL Replacement Pattern: ', 'bp-smp' ) ?>
				</label>

				<input name="bp_smp[url_pattern]" id="bp_smp_url_pattern" value="<?php echo esc_attr( $url_pattern ) ?>" style="width:30%" />
				<p class="description"><?php _e( 'Use three asterisks <strong>***</strong> where you want user input to appear.', 'bp-smp' ) ?>

			</div>

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
		$return = array(
			'url_pattern' => '',
			'admin_desc'  => ''
		);

		if ( !$site ) {
			return $return;
		}

		if ( isset( $this->smp_site_data->sites[$site]['admin_desc'] ) ) {
			$return['admin_desc'] = $this->smp_site_data->sites[$site]['admin_desc'];
		}

		if ( isset( $this->smp_site_data->sites[$site]['url_pattern'] ) ) {
			$return['url_pattern'] = $this->smp_site_data->sites[$site]['url_pattern'];
		}

		return $return;
	}

	/**
	 * AJAX handler for url pattern fetcher
	 */
	function ajax_get_url_pattern() {
		// Load up the SM site data and callbacks
		$this->setup_smp_site_data();

		$site = isset( $_POST['site'] ) ? $_POST['site'] : '';

		if ( $site ) {
			$url_pattern = $this->get_url_pattern( $site );
		} else {
			// error
		}

		echo json_encode( $url_pattern );
		die();
	}

	/**
	 * When a user saves profile data, process necessary callbacks
	 */
	function save_field_data( $fielddata ) {
		global $wpdb, $bp;

		// Load up the callbacks
		$this->setup_smp_site_data();

		// Make sure that fieldmeta has been pulled up
		$this->load_fieldmeta();

		// Check to see whether this field is an SM field, and if so, call the callback
		if ( isset( $this->fieldmeta[$fielddata->field_id] ) ) {
			// We'll need the ID of the field in question. Annoyingly, this is sometimes
			// not passed with xprofile_data_after_save. In the future I'll patch BP
			// accordingly, but for now, we'll do an extra lookup if necessary.
			if ( empty( $fielddata->id ) ) {
				if ( !$fielddata->id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$bp->profile->table_name_data} WHERE user_id = %d AND field_id = %d", $fielddata->user_id, $fielddata->field_id ) ) )
					return;
			}

			$site_id = $this->fieldmeta[$fielddata->field_id]['site'];

			// Get the callback function for the field
			$callback = isset( $this->smp_site_data->sites[$site_id]['callback'] ) ? $this->smp_site_data->sites[$site_id]['callback'] : '';

			// Run the callback
			$smp_data = call_user_func_array( $callback, array( $fielddata, $this->fieldmeta[$fielddata->field_id] ) );

			// Create the HTML
			$smp_data['html'] = $this->create_field_html( $smp_data );

			// Save to the database
			bp_xprofile_update_fielddata_meta( $fielddata->id, 'bp_smp_data', $smp_data );
		}
	}

	/**
	 * @param str $type 'icon', 'text', or 'both'.
	 *              - 'icon' will display the icon only, or text if icon not available
	 *		- 'text' will display the text only
	 *		- 'both' displays icon followed by text, eg [twitter icon] [twitter handle]
	 */
	function create_field_html( $smp_data, $type = 'icon' ) {
		if ( !empty( $smp_data['html'] ) ) {
			// If the callback created the HTML for us, no need for further processing
			$html = $smp_data['html'];
		} else {
			// If no 'title' was provided, fall back on text
			if ( empty( $smp_data['title'] ) ) {
				$smp_data['title'] = isset( $smp_data['text'] ) ? $smp_data['text'] : '';
			}

			// Create the content of the field first (the image, text, or both)
			switch ( $type ) {
				case 'both' :
					$content = isset( $smp_data['icon'] ) ? $this->create_image_html_from_smp_data( $smp_data ) : '';
					$content .= isset( $smp_data['text'] ) ? $smp_data['text'] : '';
					break;

				case 'text' :
					$content = isset( $smp_data['icon'] ) ? $smp_data['icon'] : '';
					break;

				case 'icon' :
				default :
					$content = isset( $smp_data['icon'] ) ? $this->create_image_html_from_smp_data( $smp_data ) : '';
					break;
			}

			if ( !empty( $smp_data['url'] ) ) {
				$html = '<a href="' . $smp_data['url'] . '" title="' . $smp_data['title'] . '">' . $content . '</a>';
			} else {
				$html = $content;
			}
		}

		return apply_filters( 'bp_smp_create_field_html', $html, $smp_data );
	}

	function create_image_html_from_smp_data( $smp_data ) {
		$icon = $smp_data['icon'];
		$alt  = $smp_data['title'];

		return apply_filters( 'bp_smp_create_image_html_from_smp_data', '<img src="' . $icon . '" alt="' . $alt . '" />' );
	}

	/**
	 * Get the saved fieldmeta out of the xprofile meta db table, and load into the object
	 */
	function load_fieldmeta() {
		global $wpdb, $bp;

		// This only needs to be loaded once per page load
		if ( !$this->fieldmeta ) {
			// Get the saved fieldmeta out of the database
			$fieldmeta = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$bp->profile->table_name_meta} WHERE meta_key = 'bp_smp_data' AND object_type = 'field'" ) );

			// Put it in a proper array, keyed by field id
			$this->fieldmeta = array();
			foreach ( (array)$fieldmeta as $field ) {
				// Sometimes the field is saved with a blank site. Don't include.
				$data = maybe_unserialize( $field->meta_value );

				if ( !empty( $data['site'] ) ) {
					$this->fieldmeta[$field->object_id] = $data;
				}
			}
		}
	}

	function modify_profile_loop( $has_profile ) {
		global $profile_template;

		// We only want to modify the loop if this is a public profile
		if ( !bp_is_user_profile() || bp_is_user_profile_edit() ) {
			return $has_profile;
		}

		$this->load_fieldmeta();

		// While we're looping through, grab the ids and put them in a property for later
		// access
		$this_user_data_ids = array();

		foreach( $profile_template->groups as $group_key => $group ) {
			foreach( $group->fields as $field_key => $field ) {
				$this_field_id = (int)$field->id;
				if ( isset( $this->fieldmeta[$this_field_id] ) ) {
					unset( $profile_template->groups[$group_key]->fields[$field_key] );
				}

				$this_user_data_ids[] = $this_field_id;

				// Reset indexes
				$profile_template->groups[$group_key]->fields = array_values( $profile_template->groups[$group_key]->fields );
			}

			// If we've emptied the group, remove it now
			if ( empty( $group->fields ) ) {
				unset( $profile_template->groups[$group_key] );
			}
		}

		if ( empty( $this->this_user_data_ids ) ) {
			$this->this_user_data_ids = $this_user_data_ids;
		}

		// Reset indexes
		$profile_template->groups = array_values( $profile_template->groups );

		return $has_profile;
	}

	/**
	 * Loads the displayed user's SM fields from the DB
	 */
	function setup_user_sm_fields() {
		global $wpdb, $bp;

		// Going to query the DB directly to get a list of data row IDs for the user
		if ( empty( $this->this_user_data_ids ) ) {
			$data_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$bp->profile->table_name_data} WHERE user_id = %d", bp_displayed_user_id() ) );
			$this->this_user_data_ids = (array)$data_ids;

			// Now get the user SM field data
			if ( !empty( $data_ids ) ) {
				$user_sm_fields = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$bp->profile->table_name_meta} WHERE object_id IN (" . implode( ',', $data_ids ) . ") AND object_type = 'data' AND meta_key = 'bp_smp_data'" ) );

				foreach ( $user_sm_fields as $field ) {
					$this->user_sm_fields[] = maybe_unserialize( $field );
				}
			}
		}
	}

	function display_header() {
		$this->setup_user_sm_fields();

		if ( in_array( 'header', $this->settings['display'] ) ) {
			$html = '<div id="bp-smp-header">';
			$html .= '<span class="bp-smp-label">' . $this->settings['label'] . '</span>';

			foreach ( $this->user_sm_fields as $field ) {
				$html .= $field['html'];
			}

			$html .= '</div>';

			echo $html;
		}
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

function bp_smp_load_component() {
	global $bp;

	$bp->social_media_profiles = new BP_Social_Media_Profiles;
}
add_action( 'bp_loaded', 'bp_smp_load_component' );

?>