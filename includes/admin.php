<?php

/**
 * Functions for handling/displaying stuff on the Dashboard
 */

class BP_SMP_Admin {
	function __construct() {
		$this->setup_hooks();

		wp_register_style( 'bp-smp-admin-css', BP_SMP_PLUGIN_URL . 'css/admin.css' );
		wp_register_script( 'bp-smp-admin-js', BP_SMP_PLUGIN_URL . 'js/admin.js', array( 'jquery' ) );
	}

	function setup_hooks() {
		add_action( bp_core_admin_hook(), array( &$this, 'admin_menu' ) );

		// Catch save submits
		add_action( 'admin_init', array( &$this, 'admin_submit' ) );
	}

	function admin_menu() {
		$hook = add_submenu_page( 'bp-general-settings', __( 'Social Media Profiles', 'bp-smp' ), __( 'Social Media Profiles', 'bp-smp' ), 'manage_options', 'bp-smp', array( &$this, 'admin_page' ) );

		add_action( "admin_print_styles-$hook", 'bp_core_add_admin_menu_styles' );
		add_action( "admin_print_scripts-$hook", array( &$this, 'enqueue_scripts' ) );
		add_action( "admin_print_styles-$hook", array( &$this, 'enqueue_styles' ) );
	}

	function admin_submit() {
		if ( isset( $_POST['bp-smp-submit'] ) ) {
			if ( !is_super_admin() ) {
				return;
			}

			check_admin_referer( 'bp_smp' );

			$save_data = $_POST['bp_smp'];

			// Make sure that there is an empty 'display' array if no data is sent
			if ( !isset( $save_data['display'] ) ) {
				$save_data['display'] = array();
			}

			bp_update_option( 'bp_smp_settings', $save_data );

			// Redirect to avoid any refresh issues
			$redirect_url = add_query_arg( 'page', 'bp-smp', is_network_admin() ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' ) );
			wp_redirect( $redirect_url );
		}
	}

	function admin_page() {

		// Pull up the existing values
		$settings = bp_get_option( 'bp_smp_settings' );

		$defaults = array(
			'display'         => array( 'inline' ),
			'label'           => __( 'Follow me online: ', 'bp-smp' ),
			'inline_position' => 'top',
			'replace_field'   => ''
		);

		$r = wp_parse_args( $settings, $defaults );
		extract( $r );

		?>

		<div class="wrap">
			<?php screen_icon( 'buddypress' ); ?>

			<h2><?php _e( 'BuddyPress Social Media Profiles', 'buddypress'); ?></h2>

			<form method="post" action="">

			<table class="form-table">

				<tr>
					<th scope="row">
						<?php _e( 'Display location', 'bp-smp' ) ?>
					</th>

					<td>
						<input type="checkbox" name="bp_smp[display][]" value="header" <?php if ( in_array( 'header', $display ) ) : ?>checked="checked"<?php endif ?> /> <?php _e( 'Member header', 'bp-smp' ) ?>
						<p class="description"><?php _e( 'Fields marked as Social Media fields will be displayed in each user\'s header', 'bp-smp' ) ?></p>

						<input type="checkbox" name="bp_smp[display][]" value="inline" id="bp-smp-display-inline" <?php if ( in_array( 'inline', $display ) ) : ?>checked="checked"<?php endif ?>/> <?php _e( 'Profile page', 'bp-smp' ) ?>

						<div id="inline-opts"<?php if ( !in_array( 'inline', $display ) ) : ?> style="display:none;"<?php endif ?>>
							<label for="bp_smp[inline_position]"><?php _e( 'Position:', 'bp-smp' ) ?></label>

							<ul>
								<li><input type="radio" name="bp_smp[inline_position]" value="top" <?php checked( $inline_position, 'top' ) ?>/> <?php _e( 'Top', 'bp-smp' ) ?> - <span class="description"><?php _e( 'Social media profiles will be displayed at the top of profiles', 'bp-smp' ) ?></span></li>
								<li><input type="radio" name="bp_smp[inline_position]" value="bottom" <?php checked( $inline_position, 'bottom' ) ?>/> <?php _e( 'Bottom', 'bp-smp' ) ?> - <span class="description"><?php _e( 'Social media profiles will be displayed at the bottom of profiles', 'bp-smp' ) ?></span></li>
								<li>
									<input type="radio" name="bp_smp[inline_position]" value="replace" <?php checked( $inline_position, 'replace' ) ?> id="inline-replace" /> <?php _e( 'Replace profile field', 'bp-smp' ) ?>

									<?php $groups = BP_XProfile_Group::get( array( 'fetch_fields' => true ) ); ?>

									<?php if ( !empty( $groups ) ) : ?>

									<select name="bp_smp[replace_field]" id="inline-replace-field">

									<option value="" <?php checked( $replace_field, '' ) ?>><?php _e( '- Select One -', 'bp-smp' ) ?></option>

									<?php foreach ( $groups as $group ) : foreach( $group->fields as $field ) : ?>
										<option value="<?php echo esc_attr( $field->id ) ?>" <?php selected( $replace_field, $field->id ) ?>><?php echo esc_html( $field->name ) ?></option>
									<?php endforeach; endforeach ?>

									</select>

									<span class="description"><?php _e( 'Social media profiles will <strong>replace</strong> one of your existing profile fields.', 'bp-smp' ) ?></span>

									<?php endif ?>
								</li>
							</ul>
						</div>

						<p class="description"><?php _e( 'Fields marked as Social Media fields will be displayed as part of each user\'s public profile.', 'bp-smp' ) ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<?php _e( 'Label', 'bp-smp' ) ?>
					</th>

					<td>
						<input type="text" name="bp_smp[label]" value="<?php echo esc_attr( $label ) ?>" />
						<p class="description"><?php _e( 'Text that will precede the social media fields on the public profile or user header.', 'bp-smp' ) ?></p>

					</td>
				</tr>

			</table>

			<?php wp_nonce_field( 'bp_smp' ) ?>
			<input type="submit" name="bp-smp-submit" class="button-primary" value="<?php _e( "Save Settings", 'bp-smp' ) ?>" />

			</form>
		</div>

		<?php
	}

	function enqueue_scripts() {
		wp_enqueue_script( 'bp-smp-admin-js' );
	}

	function enqueue_styles() {
		wp_enqueue_style( 'bp-smp-admin-css' );
	}
}

?>