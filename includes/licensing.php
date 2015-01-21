<?php


/**
 * Returns all registered addons.
 *
 * @since 4.4.5
 */
function wprss_get_addons() {
	return apply_filters( 'wprss_register_addon', array() );
}


/**
 * Calls the EDD Software Licensing API to perform licensing tasks on the addon's store server.
 *
 * @since 4.4.5
 */
function wprss_edd_licensing_api( $addon, $license_key = NULL, $action = 'check_license', $return = 'license' ) {
	// If no license argument was given
	if ( $license_key === NULL ) {
		// Get the license key
		$license_key = wprss_get_license_key( $addon );
	}
	// Get the license status from the DB
	$license_status = wprss_get_license_status( $addon );

	// Prepare constants
	$item_name = strtoupper( $addon );
	$item_name_constant = constant( "WPRSS_{$item_name}_SL_ITEM_NAME" );
	$store_url_constant = constant( "WPRSS_{$item_name}_SL_STORE_URL" );

	// data to send in our API request
	$api_params = array(
		'edd_action'	=> $action,
		'license'		=> sanitize_text_field( $license_key ),
		'item_name'		=> urlencode( $item_name_constant ),
		'url'			=> urlencode( network_site_url() ),
		'time'			=> time(),
	);

	// Send the request to the API
	$response = wp_remote_get( add_query_arg( $api_params, $store_url_constant ) );

	// If the response is an error, return the value in the DB
	if ( is_wp_error( $response ) ) {
		return $license_status;
	}

	// decode the license data
	$license_data = json_decode( wp_remote_retrieve_body( $response ) );

	// Update the DB option
	$license_statuses = get_option( 'wprss_settings_license_statuses' );
	$license_statuses["{$addon}_license_status"] = $license_data->license;
	$license_statuses["{$addon}_license_expires"] = $license_data->expires;
	update_option( 'wprss_settings_license_statuses', $license_statuses );

	// Return the data
	if ( strtoupper( $return ) === 'ALL' ) {
		return $license_data;
	} else {
		return $license_data->$return;
	}
}


/**
 * Returns the license status. Also updates the status in the DB.
 *
 * @since 4.4.5
 */
function wprss_edd_check_license( $addon, $license_key = NULL, $return = 'license' ) {
	return wprss_edd_licensing_api( $addon, $license_key, 'check_license', $return );
}


/**
 * Activates an addon's license.
 *
 * @since 4.4.5
 */
function wprss_edd_activate_license( $addon, $license_key = NULL ) {
	return wprss_edd_licensing_api( $addon, $license_key, 'activate_license' );
}


/**
 * Deactivates an addon's license.
 *
 * @since 4.4.5
 */
function wprss_edd_deactivate_license( $addon, $license_key = NULL ) {
	return wprss_edd_licensing_api( $addon, $license_key, 'deactivate_license' );
}


/**
* Returns an array of the default license settings. Used for plugin activation.
*
* @since 4.4.5
*
*/
function wprss_default_license_settings( $addon ) {
	// Set up the default license settings
	$settings = apply_filters(
		'wprss_default_license_settings',
		array(
			"{$addon}_license_key"		=> FALSE,
			"{$addon}_license_status"	=> 'invalid',
			"{$addon}_license_expires"	=> NULL
		)
	);

	// Return the default settings
	return $settings;
}


/**
 * Returns the saved license code.
 *
 * @since 4.4.5
 */
function wprss_get_license_key( $addon ) {
	// Get default and current options
	$defaults = wprss_default_license_settings( $addon );
	$keys = get_option( 'wprss_settings_license_keys', array() );
	// Prepare the array key and target
	$k = "{$addon}_license_key";
	// Return the appropriate value
	return isset( $keys["{$addon}_license_key"] )? $keys[$k] : $defaults[$k];
}


/**
 * Returns the saved license code.
 *
 * @since 4.4.5
 */
function wprss_get_license_status( $addon ) {
	// Get the default and current options
	$defaults = wprss_default_license_settings( $addon );
	$statuses = get_option( 'wprss_settings_license_statuses', array() );
	// Prepare the key
	$k = "{$addon}_license_status";
	// Return the appropriate value
	return isset( $statuses["{$addon}_license_status"] )? $statuses[$k] : $defaults[$k];
}


/**
 * Returns the saved license expiry.
 *
 * @since 4.6.7
 */
function wprss_get_license_expiry( $addon ) {
	// Get default and current options
	$defaults = wprss_default_license_settings( $addon );
	$statuses = get_option( 'wprss_settings_license_statuses', array() );
	// Prepare the key
	$k = "{$addon}_license_expires";
	// Return the appropriate value
	return isset( $statuses[$k] ) ? $statuses[$k] : $defaults[$k];
}


add_action( 'wp_ajax_wprss_ajax_manage_license', 'wprss_ajax_manage_license' );
/**
 * Handles the AJAX request to check a license.
 *
 * @since 4.7
 */
function wprss_ajax_manage_license() {
	// Get and sanitize the addon ID we're checking.
	if ( isset($_GET['addon']) ) {
		$addon = sanitize_text_field($_GET['addon']);
	} else {
		wprss_echo_error_and_die( __('No addon ID', WPRSS_TEXT_DOMAIN ));
	}

	// Check what we've been asked to do with the license.
	if ( isset($_GET['event']) ) {
		$event = sanitize_text_field($_GET['event']);

		if ($event !== 'activate' && $event !== 'deactivate') {
			wprss_echo_error_and_die( __('Invalid event specified', WPRSS_TEXT_DOMAIN), $addon);
		}
	} else {
		wprss_echo_error_and_die( __('No event specified', WPRSS_TEXT_DOMAIN), $addon);
	}

	// Get and sanitize the license that was entered.
	if ( isset($_GET['license']) ) {
		$license = sanitize_text_field($_GET['license']);
	} else {
		wprss_echo_error_and_die( __('No license', WPRSS_TEXT_DOMAIN), $addon);
	}

	// Check the nonce for this particular add-on's validation button.
	if ( isset($_GET['nonce']) ) {
		$nonce = sanitize_text_field($_GET['nonce']);
		$nonce_id = "wprss_{$addon}_license_nonce";

		if ( !wp_verify_nonce($nonce, $nonce_id) ) {
			wprss_echo_error_and_die( __('Bad nonce', WPRSS_TEXT_DOMAIN), $addon);
		}
	} else {
		wprss_echo_error_and_die( __('No nonce', WPRSS_TEXT_DOMAIN), $addon);
	}

	// Call the appropriate EDD licensing function.
	if ($event === 'activate') {
		$status = wprss_edd_activate_license($addon, $license);
	} else if ($event === 'deactivate') {
		$status = wprss_edd_deactivate_license($addon, $license);
	} else {
		wprss_echo_error_and_die( __('Invalid event specified', WPRSS_TEXT_DOMAIN), $addon);
	}

	// Update the license key stored in the DB.
	$license_keys = get_option('wprss_settings_license_keys', array());
	$license_keys[$addon . '_license_key'] = $license;
	update_option('wprss_settings_license_keys', $license_keys);

	// Assemble the JSON data to return.
	$ret = array();

	// Set the validity of the license.
	if ( $status === 'site_inactive' ) $status = 'inactive';
	if ( $status === 'item_name_mismatch' ) $status = 'invalid';
	$ret['validity'] = $status;

	// Set the addon ID for use in the callback.
	$ret['addon'] = $addon;

	// Set the HTML markup for the new button and validity display.
	$ret['html'] = wprss_get_activate_license_button($addon);

	// Return the JSON data.
	echo json_encode($ret);
	die();
}


add_action( 'wp_ajax_wprss_ajax_fetch_license', 'wprss_ajax_fetch_license' );
/**
 * Handles the AJAX request to fetch a license's information.
 *
 * @since 4.7
 */
function wprss_ajax_fetch_license() {
	// Get and sanitize the addon ID we're checking.
	if ( isset($_GET['addon']) ) {
		$addon = sanitize_text_field($_GET['addon']);
	} else {
		wprss_echo_error_and_die( __('No addon ID', WPRSS_TEXT_DOMAIN ));
	}

	// Get the license information from EDD
	$ret = wprss_edd_check_license( $addon, NULL, 'ALL' );

	echo json_encode($ret);
	die();
}


/**
 * Helper function that echoes a JSON error along with the new
 * activate/deactivate license button HTML markup and then die()s.
 *
 * @since 4.7
 */
function wprss_echo_error_and_die($msg, $addon = '') {
	$ret = array(
		'error' => $msg,
		'html' => wprss_get_activate_license_button($addon)
	);

	echo json_encode($ret);
	die();
}


add_action( 'wprss_admin_init', 'wprss_license_settings', 100 );
/**
 * Adds the license sections and settings for registered add-ons.
 *
 * @since 4.4.5
 */
function wprss_license_settings() {
	$addons = wprss_get_addons();
	foreach( $addons as $addon_id => $addon_name ) {
		// Settings Section
		add_settings_section(
			"wprss_settings_{$addon_id}_licenses_section",
			$addon_name .' '. __( 'License', WPRSS_TEXT_DOMAIN ),
			'__return_empty_string',
			'wprss_settings_license_keys'
		);
		// License key field
		add_settings_field(
			"wprss_settings_{$addon_id}_license",
			__( 'License Key', WPRSS_TEXT_DOMAIN ),
			'wprss_license_key_field',
			'wprss_settings_license_keys',
			"wprss_settings_{$addon_id}_licenses_section",
			array( $addon_id )
		);
		// Activate license button
		add_settings_field(
			"wprss_settings_{$addon_id}_activate_license",
			__( 'Activate License', WPRSS_TEXT_DOMAIN ),
			'wprss_activate_license_button',
			'wprss_settings_license_keys',
			"wprss_settings_{$addon_id}_licenses_section",
			array( $addon_id )
		);
	}
}


/**
 * Renders the license field for a particular add-on.
 *
 * @since 4.4.5
 */
function wprss_license_key_field( $args ) {
	$addon_id = $args[0];
	$license = wprss_get_license_key( $addon_id ); ?>
	<input id="wprss-<?php echo $addon_id; ?>-license-key" name="wprss_settings_license_keys[<?php echo $addon_id; ?>_license_key]"
		   type="text" value="<?php echo esc_attr( $license ); ?>" style="width: 300px;"
	/>
	<label class="description" for="wprss-<?php echo $addon_id; ?>-license-key">
		<?php _e( 'Enter your license key', WPRSS_TEXT_DOMAIN ); ?>
	</label><?php
}


/**
 * Renders the activate/deactivate license button for a particular add-on.
 *
 * @since 4.4.5
 */
function wprss_activate_license_button( $args ) {
	$addon_id = $args[0];
	$data = wprss_edd_check_license( $addon_id, NULL, 'ALL' );
	$status = is_string( $data ) ? $data : $data->license;
	if ( $status === 'site_inactive' ) $status = 'inactive';
	if ( $status === 'item_name_mismatch' ) $status = 'invalid';

	$valid = $status == 'valid';
	$btn_text = $valid ? 'Deactivate License' : 'Activate License';
	$btn_name = "wprss_{$addon_id}_license_" . ( $valid? 'deactivate' : 'activate' );
	$btn_class = "button-" . ( $valid ? 'deactivate' : 'activate' ) . "-license";
	wp_nonce_field( "wprss_{$addon_id}_license_nonce", "wprss_{$addon_id}_license_nonce" ); ?>

	<input type="button" class="<?php echo $btn_class; ?> button-process-license button-secondary" name="<?php echo $btn_name; ?>" value="<?php _e( $btn_text, WPRSS_TEXT_DOMAIN ); ?>" />
	<span id="wprss-<?php echo $addon_id; ?>-license-status-text">
		<strong><?php _e('Status', WPRSS_TEXT_DOMAIN); ?>:
		<span class="wprss-<?php echo $addon_id; ?>-license-<?php echo $status; ?>">
				<?php _e( ucfirst($status), WPRSS_TEXT_DOMAIN ); ?>
				<?php if ( $status === 'valid' ) : ?>
					<i class="fa fa-check"></i>
				<?php elseif( $status === 'invalid' || $status === 'expired' ): ?>
					<i class="fa fa-times"></i>
				<?php elseif( $status === 'inactive' ): ?>
					<i class="fa fa-warning"></i>
				<?php endif; ?>
			</strong>
		</span>
	</span>

	<p>
		<?php
			$license_key = wprss_get_license_key( $addon_id );
			if ( ! empty( $license_key ) ) :
				if ( is_object( $data ) ) :
					$acts_current = $data->site_count;
					$acts_left = $data->activations_left;
					$acts_limit = $data->license_limit;
					$expires = $data->expires;
					$expires = substr( $expires, 0, strpos( $expires, " " ) );
					?>
					<small>
						<?php if ( strtotime($expires) < strtotime("+2 weeks") ) : ?>
						<?php $renewal_url = esc_attr(WPRSS_SL_STORE_URL . '/checkout/?edd_license_key=' . $license_key); ?>
						<a href="<?php echo $renewal_url; ?>"><?php _e('Renew your license to continue receiving updates and support.', WPRSS_TEXT_DOMAIN); ?></a>
						<br/>
						<?php endif; ?>
						<strong><?php _e('Activations', WPRSS_TEXT_DOMAIN); ?>:</strong>
							<?php echo $acts_current.'/'.$acts_limit; ?> (<?php echo $acts_left; ?> left)
						<br/>
						<strong><?php _e('Expires on', WPRSS_TEXT_DOMAIN); ?>:</strong>
							<code><?php echo $expires; ?></code>
						<br/>
						<strong><?php _e('Registered to', WPRSS_TEXT_DOMAIN); ?>:</strong>
							<?php echo $data->customer_name; ?> (<code><?php echo $data->customer_email; ?></code>)
					</small>
				<?php else: ?>
					<small><?php _e('Failed to get license information. This is a temporary problem. Check your internet connection and try again later.', WPRSS_TEXT_DOMAIN); ?></small>
				<?php endif; ?>
			<?php endif;
		?>
	</p>

	<style type="text/css">
		.wprss-<?php echo $addon_id; ?>-license-valid {
			color: green;
		}
		.wprss-<?php echo $addon_id; ?>-license-invalid, .wprss-<?php echo $addon_id; ?>-license-expired {
			color: #b71919;
		}
		.wprss-<?php echo $addon_id; ?>-license-inactive {
			color: #d19e5b;
		}
		#wprss-<?php echo $addon_id; ?>-license-status-text {
			margin-left: 8px;
			line-height: 27px;
			vertical-align: middle;
		}
	</style>


	<?php
}


/**
 * Returns the activate/deactivate license button markup for a particular add-on.
 *
 * @since 4.7
 */
function wprss_get_activate_license_button( $addon ) {
	// Buffer the output from the rendering function.
	ob_start();

	wprss_activate_license_button(array($addon));
	$ret = ob_get_contents();

	ob_end_clean();

	return $ret;
}


add_action( 'admin_init', 'wprss_process_addon_license', 10 );
/**
 * Handles the activation/deactivation process
 *
 * @since 1.0
 */
function wprss_process_addon_license() {
	$addons = wprss_get_addons();

	// Get for each registered addon
	foreach( $addons as $id => $name ) {

		// listen for our activate button to be clicked
		if( isset( $_POST["wprss_{$id}_license_activate"] ) || isset( $_POST["wprss_{$id}_license_deactivate"] ) ) {
			// run a quick security check
			if( ! check_admin_referer( "wprss_{$id}_license_nonce", "wprss_{$id}_license_nonce" ) )
				continue; // get out if we didn't click the Activate/Deactivate button
		}

		// retrieve the license keys and statuses from the database
		$license = wprss_get_license_key( $id );
		$license_statuses = get_option( 'wprss_settings_license_statuses' );

		// If the license is not saved in DB, but is included in POST
		if ( $license == '' && !empty($_POST['wprss_settings_license_keys'][$id.'_license_key']) ) {
			// Use the license given in POST
			$license = $_POST['wprss_settings_license_keys'][$id.'_license_key'];
		}

		// Prepare the action to take
		if ( isset( $_POST["wprss_{$id}_license_activate"] ) ) {
			wprss_edd_activate_license( $id, $license );
		}
		elseif ( isset( $_POST["wprss_{$id}_license_deactivate"] ) ) {
			wprss_edd_deactivate_license( $id, $license );
		}
	}
}


add_action( 'init', 'wprss_setup_edd_updater' );
/**
 * Sets up the EDD updater for all registered add-ons.
 *
 * @since 4.6.3
 */
function wprss_setup_edd_updater() {
	// Get all registered addons
	$addons = wprss_get_addons();

	// retrieve our license key from the DB
	$licenses = get_option( 'wprss_settings_license_keys' );

	// setup the updater
	if ( !class_exists( 'EDD_SL_Plugin_Updater' ) ) {
		// load our custom updater
		include ( WPRSS_INC . 'libraries/EDD_licensing/EDD_SL_Plugin_Updater.php' );
	}

	// Iterate the addons
	foreach( $addons as $id => $name ) {
		// Prepare the data
		$license = wprss_get_license_key( $id );
		$uid = strtoupper( $id );
		$name = constant("WPRSS_{$uid}_SL_ITEM_NAME");
		$version = constant("WPRSS_{$uid}_VERSION");
		$path = constant("WPRSS_{$uid}_PATH");
		// Set up an updater
		$edd_updater = new EDD_SL_Plugin_Updater( WPRSS_SL_STORE_URL, $path, array(
			'version'   => $version,				// current version number
			'license'   => $license,				// license key (used get_option above to retrieve from DB)
			'item_name' => $name,					// name of this plugin
			'author'    => 'Jean Galea'				// author of this plugin
		));
	}
}
