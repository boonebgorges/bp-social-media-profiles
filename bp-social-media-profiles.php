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

		if ( is_admin() || is_network_admin() ) {
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

		// Remove the social media fields from the loop
		add_action( 'bp_has_profile', array( &$this, 'modify_profile_loop' ) );

		// We do the display hooks in a function hooked to bp_init, so that we have access
		// to the plugin settings
		add_action( 'bp_init', array( &$this, 'setup_display_hooks' ), 15 );
	}

	function setup_display_hooks() {
		// No need to run this logic if we're not on a user page
		if ( !bp_is_user() )
			return;

		$hooks = array();

		foreach( $this->settings['display'] as $display_area ) {
			if ( 'header' == $display_area ) {
				$hooks[] = 'bp_profile_header_meta';
			} elseif ( 'inline' == $display_area ) {
				switch ( $this->settings['inline_position'] ) {
					case 'top' :
						$hooks[] = 'bp_before_profile_loop_content';
						break;

					case 'bottom' :
						$hooks[] = 'bp_after_profile_loop_content';
						break;

					case 'replace' :
						// This case is tricky. Instead of adding a display
						// hook we'll be reaching into the $profile_template
						// global and swapping out the retrieved value with
						// our markup
						add_filter( 'bp_get_the_profile_field_name', array( &$this, 'profile_field_name_swap'), 10, 3 );
						add_filter( 'bp_get_the_profile_field_value', array( &$this, 'profile_field_value_swap'), 10, 3 );

						//add_filter( 'bp_has_profile', array( &$this, 'profile_field_swap' ), 10 );

				}
			}
		}

		foreach( $hooks as $hook ) {
			add_action( $hook, array( &$this, 'display_header' ) );
		}
	}

	function profile_field_name_swap( $name ) {
		global $field;

		if ( !empty( $this->settings['replace_field'] ) && $field->id == $this->settings['replace_field'] ) {
			return $this->settings['label'];
		} else {
			return $name;
		}
	}

	function profile_field_value_swap( $value, $type, $id ) {
		if ( !empty( $this->settings['replace_field'] ) && $id == $this->settings['replace_field'] ) {
			return $this->display_markup();
		} else {
			return $value;
		}
	}

	function profile_field_swap( $has_profile ) {
		global $profile_template;

		if ( !bp_is_user_profile() || bp_is_user_profile_edit() ) {
			return $has_profile;
		}

		if ( empty( $this->settings['replace_field'] ) ) {
			return $has_profile;
		}

		foreach( $profile_template->groups as $group_key => $group ) {
			foreach( $group->fields as $field_key => $field ) {
				if ( $this->settings['replace_field'] == $field->id ) {
					$profile_template->groups[$group_key]->fields[$field_key]->name = $this->settings['label'];

					$profile_template->groups[$group_key]->fields[$field_key]->data->value = $this->display_markup();
				}
			}
		}

		return $has_profile;
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

	function save_admin_field( $field ) {
		if ( isset( $_POST['bp_smp'] ) ) {
			// When creating a new field, no field_id will have been set yet. We'll
			// look it up based on the $field object passed to the hook
			if ( empty( $this->field_id ) ) {
				$this->field_id = BP_XProfile_Field::get_id_from_name( $field->name );
			}

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
		if ( isset( $this->field_smp_data['url_pattern'] ) && $this->field_smp_data['url_pattern'] != '' ) {
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
			$fieldmeta = $wpdb->get_results( "SELECT * FROM {$bp->profile->table_name_meta} WHERE meta_key = 'bp_smp_data' AND object_type = 'field'" );

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
	 * Loads the displayed user's SM fields
	 */
	function setup_user_sm_fields() {
		global $bp;

		$this->load_fieldmeta();
		$this->setup_smp_site_data();

		// Get all of the user's xprofile fields
		$user_xprofile_fields = BP_XProfile_ProfileData::get_all_for_user( bp_displayed_user_id() );

		// Go through all the fields, pick out the ones with bp_smp_data, and store their ids and values
		// for us on display
		foreach( $user_xprofile_fields as $field_name => $xprofile_field ) {
			if ( !isset($xprofile_field['field_id']) ) {
				continue;
			}
			$smp_field_id = $xprofile_field['field_id'];
			if ( $this->is_smp_field( $smp_field_id ) ) {
				$field_bp_smp_data = bp_xprofile_get_meta( $smp_field_id, 'field', 'bp_smp_data' );
				if ( isset( $field_bp_smp_data['site'] ) && $field_bp_smp_data['site'] != '' ) {
					$smp_field_value = xprofile_get_field_data( $smp_field_id, bp_displayed_user_id() );
					$site_id = strtolower( $field_name );
					// Get the callback function for the field
					$callback = isset( $this->smp_site_data->sites[$site_id]['callback'] ) ? $this->smp_site_data->sites[$site_id]['callback'] : '';
					// If the user hasn't supplied a URL pattern, check to make sure one hasn't been defined in the defaults
					// If one has, pass it to the callback function
					if ( !isset( $this->fieldmeta[$smp_field_id]['url_pattern'] ) || $this->fieldmeta[$smp_field_id]['url_pattern'] != '' ) {
						if (  isset( $this->smp_site_data->sites[$site_id]['url_pattern'] ) && $this->smp_site_data->sites[$site_id]['url_pattern'] != '' ) {
							$url_pattern = $this->smp_site_data->sites[$site_id]['url_pattern'];
						} else {
							$url_pattern = $this->fieldmeta[$smp_field_id]['url_pattern'];
						}
					}
					// Run the callback
					$smp_data = call_user_func_array( $callback, array( $smp_field_value, $url_pattern ) );

					$smp_data = apply_filters( "bp_smp_" . $callback[1], $smp_data, $smp_field_value, $this->fieldmeta[$smp_field_id] );
					$smp_data['html'] = $this->create_field_html( $smp_data );

					$this->user_sm_fields[] = $smp_data;
				}

			}
		}
	}

	function display_header() {
		$this->setup_user_sm_fields();

		if ( empty( $this->user_sm_fields ) ) {
			return;
		}

		if ( in_array( 'header', $this->settings['display'] ) ) {
			echo $this->display_markup();
		}
	}
	function is_smp_field( $field_id ) {
		$this->load_fieldmeta();

		$smp_field_ids = array_keys( $this->fieldmeta );
		if ( in_array( $field_id, $smp_field_ids ) ) {
			return true;
		}
		return false;
	}
	function display_markup() {
		$html = '<div id="bp-smp-header">';
		$html .= '<span class="bp-smp-label">' . $this->settings['label'] . '</span>';

		foreach ( $this->user_sm_fields as $field ) {
			$html .= $field['html'];
		}

		$html .= '</div>';
		return apply_filters( 'bp_smp_display_markup', $html );
	}

	function admin_styles() {
		if ( isset( $_GET['page'] ) && 'bp-profile-setup' == $_GET['page'] ) {
			wp_enqueue_style( 'bp-smp-admin-css' );
		}
	}

	function admin_scripts() {
		if ( isset( $_GET['page'] ) && 'bp-profile-setup' == $_GET['page'] ) {
			wp_enqueue_script( 'bp-smp-admin-js' );
		}
	}
}

function bp_smp_load_component() {
	global $bp;

	$bp->social_media_profiles = new BP_Social_Media_Profiles;
}
add_action( 'bp_loaded', 'bp_smp_load_component' );

?>
