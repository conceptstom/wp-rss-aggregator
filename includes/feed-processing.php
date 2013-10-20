<?php
    /**
     * Feed processing related functions
     *
     * @package WPRSSAggregator
     */


    /**
     * Change the default feed cache recreation period to 2 hours
     *
     * Probably not needed since we are now disabling caching altogether
     *
     * @since 2.1
     */
    function wprss_feed_cache_lifetime( $seconds )
    {
        return 1; // one second
    }


    /**
     * Disable caching of feeds in transients, we don't need it as we are storing them in the wp_posts table
     *
     * @since 3.0
     */
    function wprss_do_not_cache_feeds( &$feed ) {
        $feed->enable_cache( false );
    }


    /**
     * Parameters for query to get all feed sources
     *
     * @since 3.0
     */
    function wprss_get_all_feed_sources() {
        // Get all feed sources
        $feed_sources = new WP_Query( apply_filters(
            'wprss_get_all_feed_sources',
            array(
                'post_type'      => 'wprss_feed',
                'post_status'    => 'publish',
                'cache_results'  => false,   // Disable caching, used for one-off queries
                'no_found_rows'  => true,    // We don't need pagination, so disable it
                'posts_per_page' => -1
            )
        ) );
        return $feed_sources;
    }


    /**
     * Parameters for query to get feed sources
     *
     * @since 3.0
     */
    function wprss_get_feed_source() {
        // Get all feed sources
        $feed_sources = new WP_Query( apply_filters(
            'wprss_get_all_feed_sources',
            array(
                'post_type'      => 'wprss_feed',
                'post_status'    => 'publish',
                'cache_results'  => false,   // Disable caching, used for one-off queries
                'no_found_rows'  => true,    // We don't need pagination, so disable it
                'posts_per_page' => -1
            )
        ) );
        return $feed_sources;
    }


    /**
     * Database query to get existing permalinks
     *
     * @since 3.0
     */
    function get_existing_permalinks( $feed_ID ) {
        global $wpdb;

        $existing_permalinks = $wpdb->get_col(
                                        "SELECT meta_value
                                        FROM $wpdb->postmeta
                                        WHERE meta_key = 'wprss_item_permalink'
                                        AND post_id IN ( SELECT post_id FROM $wpdb->postmeta WHERE meta_value = $feed_ID )"
        );

        return $existing_permalinks;
    }


    /**
     * Fetch the feeds from a feed item url
     *
     * @since 3.0
     */
    function wprss_get_feed_items( $feed_url ) {
        $general_settings = get_option( 'wprss_settings_general' );
        $feed_item_limit = $general_settings['limit_feed_items_imported'];

        // Don't fetch the feed if feed item limit is 0, there's no need, huge speed improvement
        if ( $feed_item_limit == 0 ) return;

        add_filter( 'wp_feed_cache_transient_lifetime' , 'wprss_feed_cache_lifetime' );

        /* Disable caching of feeds */
        add_action( 'wp_feed_options', 'wprss_do_not_cache_feeds' );
        /* Fetch the feed from the soure URL specified */
        $feed = fetch_feed( $feed_url );
        //$feed = new SimplePie();
        //$feed->set_feed_url( $feed_url );
        //$feed->init();
        /* Remove action here because we only don't want it active feed imports outside of our plugin */
        remove_action( 'wp_feed_options', 'wprss_do_not_cache_feeds' );

        //$feed = wprss_fetch_feed( $feed_url );
        remove_filter( 'wp_feed_cache_transient_lifetime' , 'wprss_feed_cache_lifetime' );
        
        if ( !is_wp_error( $feed ) ) {

            // Figure out how many total items there are, but limit it to the number of items set in options.
            $maxitems = $feed->get_item_quantity( $feed_item_limit );

            if ( $maxitems == 0 ) { return; }

            // Build an array of all the items, starting with element 0 (first element).
            $items = $feed->get_items( 0, $maxitems );
            return $items;
        }

        else { return; }
    }


    /**
     * Insert a WPRSS feed item post
     *
     * @since 3.0
     */
    function wprss_items_insert_post( $items, $feed_ID ) {

        // Gather the permalinks of existing feed item's related to this feed source
        $existing_permalinks = get_existing_permalinks( $feed_ID );

        foreach ( $items as $item ) {

            // normalize permalink to pass through feed proxy URL
            $permalink = $item->get_permalink();

            // CHECK PERMALINK FOR VIDEO HOSTS : YOUTUBE, VIMEO AND DAILYMOTION
			$found_video_host = preg_match( '/http[s]?:\/\/(www\.)?(youtube|dailymotion|vimeo)\.com\/(.*)/i', $permalink, $matches );
			
			// If video host was found
			if ( $found_video_host !== 0 && $found_video_host !== FALSE ) {
			
				// Get general options
				$options = get_option( 'wprss_settings_general' );
				// Get the video link option entry, or false if it does not exist
				$video_link = ( isset($options['video_link']) )? $options['video_link'] : 'false';
			
				// If the video link option is true, change the video URL to its repective host's embedded
				// video player URL. Otherwise, leave the permalink as is.
				if ( strtolower( $video_link ) === 'true' ) {
					$host = $matches[2];
					switch( $host ) {
						case 'youtube':
							preg_match( '/(&|\?)v=([^&]+)/', $permalink, $yt_matches );
							$permalink = 'http://www.youtube.com/embed/' . $yt_matches[2];
							break;
						case 'vimeo':
							preg_match( '/(\d*)$/i', $permalink, $vim_matches );
							$permalink = 'http://player.vimeo.com/video/' . $vim_matches[0];
							break;
						case 'dailymotion':
							preg_match( '/(\.com\/)(video\/)(.*)/i', $permalink, $dm_matches );
							$permalink = 'http://www.dailymotion.com/embed/video/' . $dm_matches[3];
							break;
					}
				}
			}


            /*
            $response = wp_remote_head( $permalink );
            if ( !is_wp_error(  $response ) && isset( $response['headers']['location'] ) ) {
                $permalink = current( explode( '?', $response['headers']['location'] ) );
            }*/

            // Check if newly fetched item already present in existing feed items,
            // if not insert it into wp_posts and insert post meta.
            if ( ! ( in_array( $permalink, $existing_permalinks ) ) ) {

				// Apply filters that determine if the feed item should be inserted into the DB or not.
				$item = apply_filters( 'wprss_insert_post_item_conditionals', $item, $feed_ID, $permalink );
			
				// If the item is not NULL, continue to inserting the feed item post into the DB
				if ( $item !== NULL ) {
			
					$feed_item = apply_filters(
						'wprss_populate_post_data',
						array(
							'post_title'   => $item->get_title(),
							'post_content' => '',
							'post_status'  => 'publish',
							'post_type'    => 'wprss_feed_item',
						),
						$item
					);
				
					// Create and insert post object into the DB
					$inserted_ID = wp_insert_post( $feed_item );

					// Create and insert post meta into the DB
					wprss_items_insert_post_meta( $inserted_ID, $item, $feed_ID, $permalink );

					// Remember newly added permalink
					$existing_permalinks[] = $permalink;
				}
            }
        }
    }


    /**
     * Creates meta entries for feed items while they are being imported
     *
     * @since 2.3
     */
    function wprss_items_insert_post_meta( $inserted_ID, $item, $feed_ID, $feed_url) {

        update_post_meta( $inserted_ID, 'wprss_item_permalink', $feed_url );
        update_post_meta( $inserted_ID, 'wprss_item_description', $item->get_description() );
        update_post_meta( $inserted_ID, 'wprss_item_date', $item->get_date( 'U' ) ); // Save as Unix timestamp format
        update_post_meta( $inserted_ID, 'wprss_feed_id', $feed_ID);
        do_action( 'wprss_items_create_post_meta', $inserted_ID, $item, $feed_ID );
    }


    add_action( 'publish_wprss_feed', 'wprss_fetch_insert_feed_items', 10 );
    /**
     * Fetches feed items from source provided and inserts into db
     *
     * This function is used when inserting or untrashing a new feed source, it only gets feeds from that particular source
     *
     * @since 3.0
     */
    function wprss_fetch_insert_feed_items( $post_id ) {
        wp_schedule_single_event( time(), 'wprss_fetch_single_feed_hook', array( $post_id ) );
    }


    add_action( 'post_updated', 'wprss_updated_feed_source', 10, 3 );
    /**
     * This function is triggered just after a post is updated.
     * It checks if the updated post is a feed source, and carries out any
     * updating necassary.
     *
     * @since 3.3
     */
    function wprss_updated_feed_source( $post_ID, $post_after, $post_before ) {
        // Check if the post is a feed source and is published
        
        if ( ( $post_after->post_type == 'wprss_feed' ) && ( $post_after->post_status == 'publish' ) ) {

        	if ( isset( $_POST['wprss_limit'] ) && !empty( $_POST['wprss_limit'] ) ) {
	            // Checking feed limit change
	            // Get the limit currently saved in db, and limit in POST request
	            //$limit = get_post_meta( $post_ID, 'wprss_limit', true );
	            $limit = $_POST['wprss_limit'];
	            // Get all feed items for this source
	            $feed_sources = new WP_Query(
					array(
						'post_type'      => 'wprss_feed_item',
						'post_status'    => 'publish',
						'cache_results'  => false,   // Disable caching, used for one-off queries
						'no_found_rows'  => true,    // We don't need pagination, so disable it
						'posts_per_page' => -1,
						'orderby' 		 => 'date',
						'order' 		 => 'ASC',
						'meta_query'     => array(
							array(
								'key'     => 'wprss_feed_id',
								'value'   => $post_ID,
								'compare' => 'LIKE'
							)
						)
					)
	            );
	            // If the limit is smaller than the number of found posts, delete the feed items
	            // and re-import, to ensure that most recent feed items are present.
	            $difference = intval( $feed_sources->post_count ) - intval( $limit );
	            if ( $difference > 0 ) {
	            	// Loop and delete the excess feed items
					while ( $feed_sources->have_posts() && $difference > 0 ) {
						$feed_sources->the_post();
						wp_delete_post( get_the_ID(), true );
						$difference--;
					}
	            }
        	}
        }
    }



	add_action( 'wprss_fetch_single_feed_hook', 'wprss_fetch_insert_single_feed_items' );
	/**
	 * Fetches feed items from source provided and inserts into db
	 *
	 * @since 3.2
	 */
	function wprss_fetch_insert_single_feed_items( $feed_ID ) {

        // Get the URL and Feed Limit post meta data
        $feed_url = get_post_meta( $feed_ID, 'wprss_url', true );
		$feed_limit = get_post_meta( $feed_ID, 'wprss_limit', true );

        $feed_url = apply_filters( 'wprss_feed_source_url', $feed_url, $feed_ID );

		// Use the URL custom field to fetch the feed items for this source
		if ( filter_var( $feed_url, FILTER_VALIDATE_URL ) ) {
			$items = wprss_get_feed_items( $feed_url );

            // If the feed has its own meta limit,
            // slice the items array using the feed meta limit
            if ( !empty( $feed_limit ) )
                $items_to_insert = array_slice($items, 0, $feed_limit);
            else $items_to_insert = $items;
            
            // Insert the items into the db
			if ( !empty( $items_to_insert ) ) {
				wprss_items_insert_post( $items_to_insert, $feed_ID );
			}
		}
	}


    /**
     * Fetches all feed items from sources provided and inserts into db
     *
     * This function is used by the cron job or the debugging functions to get all feeds from all feed sources
     *
     * @since 3.0
     */
    function wprss_fetch_insert_all_feed_items() {

        // Get all feed sources
        $feed_sources = wprss_get_all_feed_sources();

        if( $feed_sources->have_posts() ) {
            // Start by getting one feed source, we will cycle through them one by one,
            // fetching feed items and adding them to the database in each pass
            while ( $feed_sources->have_posts() ) {
                $feed_sources->the_post();
				wp_schedule_single_event( time(), 'wprss_fetch_single_feed_hook', array( get_the_ID() ) );
            }
            wp_reset_postdata(); // Restore the $post global to the current post in the main query
        }
    }



    add_action( 'updated_post_meta', 'wprss_update_feed_meta', 10, 4 );
    /**
     * This function is run whenever a post is saved or updated.
     *
     * @since 3.4
     */
    function wprss_update_feed_meta( $meta_id, $post_id, $meta_key, $meta_value ) {
        $post = get_post( $post_id );
        if ( $post->post_status === 'publish' && $post->post_type === 'wprss_feed' ) {
            if ( $meta_key === 'wprss_url' )
                wprss_change_fb_url( $post_id, $meta_value );
        }
    }


    function wprss_change_fb_url( $post_id, $url ) {
        # Check if url begins with a known facebook hostname.
        if (    stripos( $url, 'http://facebook.com' ) === 0
            ||  stripos( $url, 'http://www.facebook.com' ) === 0
            ||  stripos( $url, 'https://facebook.com' ) === 0
            ||  stripos( $url, 'https://www.facebook.com' ) === 0
        ) {
            # Generate the new URL to FB Graph
            $com_index = stripos( $url, '.com' );
            $fb_page = substr( $url, $com_index + 4 ); # 4 = length of ".com"
            $fb_graph_url = 'http://graph.facebook.com' . $fb_page;
            # Contact FB Graph and get data
            $response = wp_remote_get( $fb_graph_url );
            # If the repsonse successful and has a body
            if ( !is_wp_error( $response ) && isset( $response['body'] ) ) {
                # Parse the body as a JSON string
                $json = json_decode( $response['body'], true );
                # If an id is present ...
                if ( isset( $json['id'] ) ) {
                    # Generate the final URL for this feed and update the post meta
                    $final_url = "http://www.facebook.com/feeds/page.php?format=atom10&id=" . $json['id'];
                    update_post_meta( $post_id, 'wprss_url', $final_url, $url );   
                }
            }
        }
    }


    add_action( 'trash_wprss_feed', 'wprss_delete_feed_items' );   // maybe use wp_trash_post action? wp_trash_wprss_feed
    /**
     * Delete feed items on trashing of corresponding feed source
     *
     * @since 2.0
     */
    function wprss_delete_feed_items( $postid ) {

        $args = array(
            'post_type'     => 'wprss_feed_item',
            // Next 3 parameters for performance, see http://thomasgriffinmedia.com/blog/2012/10/optimize-wordpress-queries
            'cache_results' => false,   // Disable caching, used for one-off queries
            'no_found_rows' => true,    // We don't need pagination, so disable it
            'fields'        => 'ids',   // Returns post IDs only
            'posts_per_page'=> -1,
            'meta_query'    => array(
                                    array(
                                    'key'     => 'wprss_feed_id',
                                    'value'   => $postid,
                                    'compare' => 'LIKE'
                                    )
            )
        );

        $feed_item_ids = get_posts( $args );
        foreach( $feed_item_ids as $feed_item_id )  {
                $purge = wp_delete_post( $feed_item_id, true ); // delete the feed item, skipping trash
        }
        wp_reset_postdata();
    }


    /**
     * Delete all feed items
     *
     * @since 3.0
     */
    function wprss_delete_all_feed_items() {
        $args = array(
                'post_type'      => 'wprss_feed_item',
                'cache_results'  => false,   // Disable caching, used for one-off queries
                'no_found_rows'  => true,    // We don't need pagination, so disable it
                'fields'         => 'ids',   // Returns post IDs only
                'posts_per_page' => -1,
        );

        //$feed_items = new WP_Query( $args );

        $feed_item_ids = get_posts( $args );
        foreach( $feed_item_ids as $feed_item_id )  {
                $purge = wp_delete_post( $feed_item_id, true ); // delete the feed item, skipping trash
        }
        wp_reset_postdata();
    }


    /**
     * Returns the given parameter as a string. Used in wprss_truncate_posts()
     *
     * @return string The given parameter as a string
     * @since 3.5.1
     */
    function wprss_return_as_string( $item ) {
        return "'$item'";
    }

    /**
     * Delete old feed items from the database to avoid bloat
     *
     * @since 2.0
     */
    function wprss_truncate_posts() {
        global $wpdb;
        $general_settings = get_option( 'wprss_settings_general' );

        if ( $general_settings['limit_feed_items_db'] == 0 ) {
            return;
        }

        // Set your threshold of max posts and post_type name
        $threshold = $general_settings['limit_feed_items_db'];
        $post_types = apply_filters( 'wprss_truncation_post_types', array( 'wprss_feed_item' ) );
        $post_types_str = array_map( 'wprss_return_as_string', $post_types );
        
        $post_type_list = implode( ',' , $post_types_str );

        // Query post type
        // $wpdb query allows me to select specific columns instead of grabbing the entire post object.
        $query = "
            SELECT ID, post_title FROM $wpdb->posts
            WHERE post_type IN ($post_type_list)
            AND post_status = 'publish'
            ORDER BY post_modified DESC
        ";
        
        $results = $wpdb->get_results( $query );

        // Check if there are any results
        if ( count( $results ) ){
            foreach ( $results as $post ) {
                $i++;

                // Skip any posts within our threshold
                if ( $i <= $threshold )
                    continue;

                // Let the WordPress API do the heavy lifting for cleaning up entire post trails
                $purge = wp_delete_post( $post->ID, true );
            }
        }
    }


    /**
     * Custom version of the WP fetch_feed() function, since we want custom sanitization of a feed
     *
     * Not being used at the moment, until we decide whether we can still use fetch_feed and modify its handling of sanitization
     *
     * @since 3.0
     *
     */
    /*function wprss_fetch_feed($url) {
        require_once (ABSPATH . WPINC . '/class-feed.php');

        $feed = new SimplePie();

        // $feed->set_sanitize_class( 'WP_SimplePie_Sanitize_KSES' );
        // We must manually overwrite $feed->sanitize because SimplePie's
        // constructor sets it before we have a chance to set the sanitization class
        // $feed->sanitize = new WP_SimplePie_Sanitize_KSES();

        $feed->set_cache_class( 'WP_Feed_Cache' );
        $feed->set_file_class( 'WP_SimplePie_File' );

        $feed->set_feed_url($url);
        $feed->strip_htmltags(array_merge($feed->strip_htmltags, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'a' )));
        $feed->set_cache_duration( apply_filters( 'wp_feed_cache_transient_lifetime', 12 * HOUR_IN_SECONDS, $url ) );
        do_action_ref_array( 'wp_feed_options', array( &$feed, $url ) );
        $feed->init();
        $feed->handle_content_type();

        if ( $feed->error() )
            return new WP_Error('simplepie-error', $feed->error());

        return $feed;
    }*/


    /**
     * Deletes all imported feeds and re-imports everything
     *
     * @since 3.0
     */
    function wprss_feed_reset() {
        wprss_delete_all_feed_items();
        wprss_fetch_insert_all_feed_items();
    }

  /*  add_action( 'wp_feed_options', 'wprss_feed_options' );
    function wprss_feed_options( $feed) {
        $feed->strip_htmltags(array_merge($feed->strip_htmltags, array('h1', 'a', 'img','em')));
    }

*/