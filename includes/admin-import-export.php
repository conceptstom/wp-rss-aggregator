<?php
    /**
     * Build the import/export settings page, used to import and export the plugin's settings
     * Based on http://wp.tutsplus.com/tutorials/creative-coding/creating-a-simple-backuprestore-settings-feature/
     *
     * @since 3.1
     */

	add_action( 'admin_init', 'wp_rss_aggregator_bulk_import' );
	/**
	 * Checks for the submission of a bulk import.
	 * If a bulk submission is made, creates the feed sources.
	 * 
	 * @since 4.5
	 */
	function wp_rss_aggregator_bulk_import() {
		// Check if recieving
		if ( !empty( $_POST['bulk-feeds'] ) ) {
			// Check nonce
			check_admin_referer('wprss-bulk-import', 'wprss-bulk-import');
			// Get the text
			$bulk_feeds = $_POST['bulk-feeds'];
			// Split by lines
			$lines = explode("\n", $bulk_feeds);
			// Keep a counter
			global $wprss_bulk_count;
			$wprss_bulk_count = 0;
			// Iterate each line
			foreach( $lines as $line ) {
				// Split by comma
				$parts = array_map('trim', explode(",", $line) );
				// Check if split was successful
				if ( count($parts) < 2 ) continue;
				// Prepare the feed data
				$name = $parts[0];
				$url = $parts[1];
				// Check if both name and url are set
				if ( empty($name) || empty($url) ) continue;
				$feed = array(
					'post_title'	=> $name,
					'post_status'	=> 'publish',
					'post_type'		=> 'wprss_feed'
				);
				// Insert the feed into the DB
				$inserted_id = wp_insert_post( $feed );
				// Check if an error occurred
				if ( is_wp_error($inserted_id) ) continue;
				// Set the URL
				update_post_meta($inserted_id, 'wprss_url', $url);
				// Increment the counter
				$wprss_bulk_count++;
			}
			add_action('admin_notices', 'wprss_notify_bulk_add');
		}
	}

	function wprss_notify_bulk_add() {
		global $wprss_bulk_count; ?>
		<div class="updated">
			<p><?php _e('Successfully imported', 'wprss'); ?> <code><?php echo $wprss_bulk_count; ?></code> <?php _e('feed sources','wprss'); ?>.</p>
		</div>
		<?php
	}


    add_action( 'admin_init', 'wp_rss_aggregator_export', 1 );

    /**
     * Handles exporting of aggregator settings
     *
     * @since 3.1
     */
    function wp_rss_aggregator_export() {
        if ( isset( $_POST['export'] ) && check_admin_referer( 'wprss-settings-export' ) ) {
            $blogname = str_replace( " ", "", get_option( 'blogname' ) );
            $date = date( "m-d-Y" );
            $json_name = $blogname . "-" . $date; // Naming the filename that will be generated.

            header( 'Content-Description: File Transfer' );
            header( "Content-Type: text/json; charset=" . get_option( 'blog_charset' ) );
            header( "Content-Disposition: attachment; filename=$json_name.json" );
            wp_rss_set_export_data();
            die();
        }
    }


    /**
     * Gathers relevant options, encodes them in Json and echoes the file
     *
     * @since 3.1
     */
    function wp_rss_set_export_data() {
        $options = apply_filters(
            'wprss_fields_export',
            array( 'wprss_settings_general' => get_option( 'wprss_settings_general' ) )
        );
        $json_file = json_encode( $options );

        foreach ( $options as $key => $value ) {
            $value = maybe_unserialize( $value );
            $need_options[ $key ] = $value;
        }
        $json_file = json_encode( $need_options ); // Encode data into json data
        echo $json_file;
        die();
    }


    /**
     * Notice for a successful export
     *
     * @since 3.1
     */
    function wp_rss_aggregator_export_notice() {
         echo '<div class="updated"><p>All options are export successfully.</p></div>';

    }


    /**
     * Notice for a successful import
     *
     * @since 3.1
     */
    function wp_rss_aggregator_import_notice1() {
          echo '<div class="updated"><p>' . __( 'All options are restored successfully.', 'wprss' ) . '</p></div>';

    }


    /**
     * Notice for an unsuccessful import
     *
     * @since 3.1
     */
    function wp_rss_aggregator_import_notice2() {
        echo '<div class="error"><p>' . __( 'Invalid file or file size too big.', 'wprss' ) . '</p></div>';

    }


    add_action( 'admin_init', 'wp_rss_aggregator_import' );
    /**
     * Handles the importing of settings
     *
     * @since 3.1
     */
    function wp_rss_aggregator_import(){
        global $pagenow;
        if( $pagenow == 'admin.php' ) {
            //Hope this plugin don't use admin.php for anything
            return;
        }
        elseif ( $pagenow == 'edit.php' ) {
            if ( isset( $_FILES['import'] ) && check_admin_referer( 'wprss-settings-import' ) ) {
                if ( $_FILES['import']['error'] > 0) {
                    wp_die( "Error during import" );
                } else {
                    $file_name = $_FILES['import']['name'];
                    $file_ext = strtolower( end( explode( ".", $file_name ) ) );
                    $file_size = $_FILES['import']['size'];
                    if ( ( $file_ext == "json" ) && ( $file_size < 500000 ) ) {
                        $encode_options = file_get_contents( $_FILES['import']['tmp_name'] );
                        $options = json_decode( $encode_options, true );
                        foreach ( $options as $key => $value ) {
                            update_option( $key, $value );
                        }
                        add_action( 'admin_notices', 'wp_rss_aggregator_import_notice1' );
                        do_action( 'wprss_settings_imported' );
                    }
                    else {
                        add_action( 'admin_notices', 'wp_rss_aggregator_import_notice2' );
                    }
                }
            }
        }
    }


    /**
     * Handles the import/export page display
     *
     * @since 3.1
     */
    function wprss_import_export_settings_page_display() {
        if ( !isset( $_POST['export'] ) ) { ?>
            <div class="wrap">
                <?php screen_icon( 'wprss-aggregator' ); ?>
				
				<!-- Bulk Add -->
				<h2><?php _e( 'Bulk Feed Import', 'wprss' ); ?></h2>
				<p><?php _e('Import multiple feed sources at once, by entering the name and URLs of your feeds below.', 'wprss'); ?></p>
				<p><?php _e('Separate the name and the URL using a comma on each line:', 'wprss'); ?>
					<code><?php _e('Feed Name, http://www.myfeed.com', 'wprss'); ?></code>
				</p>
				<form id="bulk-add-form" method="POST">
					<textarea rows="6" cols="80" form="bulk-add-form" name="bulk-feeds" autofocus></textarea>
					<br/>
					<?php wp_nonce_field('wprss-bulk-import', 'wprss-bulk-import'); ?>
					<input type="submit" class="button-secondary" name="bulk-add" value="Bulk Import" />
				</form>
				<hr/>
				
				<!-- Settings Import/Export -->
                <h2><?php _e( 'Import & Export Settings', 'wprss' ); ?></h2>

                <h3><?php _e( 'Export Settings', 'wprss' ); ?></h3>
                <p><?php _e( 'Click the <strong>Export Settings</strong> button to generate a file containing all the settings used by WP RSS Aggregator', 'wprss' ); ?></p>
                <p><?php _e( 'After exporting, you can either use the backup file to restore your settings to this site or to another WordPress site.</p>', 'wprss' ); ?></p>
                <?php do_action( 'wprss_export_section' ); ?>
                <form method="post">
                    <p class="submit">
                        <?php wp_nonce_field( 'wprss-settings-export' ); ?>
                        <input type="submit" name="export" value="<?php _e( 'Export Settings', 'wprss' ); ?>"  class="button" />
                    </p>
                </form>

                <h3><?php _e( 'Import Settings', 'wprss' ); ?></h3>
                <p><?php _e( 'Click the <strong>Choose file</strong> button and choose a backup file.', 'wprss' ); ?></p>
                <p><?php _e( 'Press the <strong>Import Settings</strong> button, and WordPress will do the rest for you.', 'wprss' ); ?></p>
                <?php do_action( 'wprss_import_section' ); ?>
                <form method='post' enctype='multipart/form-data'>
                    <p class="submit">
                        <?php wp_nonce_field( 'wprss-settings-import' ); ?>
                        <input type='file' name='import' />
                        <input type='submit' name='import' value="<?php _e( 'Import Settings', 'wprss' ); ?>" class="button" />
                    </p>
                </form>

                <h3><?php _e( 'Importing/Exporting Feed Sources', 'wprss' ); ?></h3>
                <p><?php _e( 'To import/export your feed sources, please use the standard WordPress <a href="' . get_admin_url() . 'import.php">Import</a> and <a href="' . get_admin_url() . 'export.php">Export</a> functionality.', 'wprss' ); ?></p>
                <p><?php _e( 'On the <a href="' . get_admin_url() . 'export.php">Export</a> page, check the <strong>Feed Sources</strong> radio button and click the <strong>Download Export File</strong> button. WordPress will then create an XML file containing all the feed sources.', 'wprss' ); ?></p>
                <p><?php _e( 'On the <a href="' . get_admin_url() . 'import.php">Import</a> page, choose the previously created file and click the <strong>Upload file and import</strong> button.', 'wprss' ); ?></p>

            </div>
        <?php
        }
    }