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
        //return 7200; // 2 hours
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


    function wprss_get_feed_items( $feed_url ) {
        add_filter( 'wp_feed_cache_transient_lifetime' , 'wprss_feed_cache_lifetime' );
       
        /* Disable caching of feeds */
        add_action( 'wp_feed_options', 'wprss_do_not_cache_feeds' ); 
        /* Fetch the feed from the soure URL specified */
        $feed = fetch_feed( $feed_url );            
        /* Remove action here because we only don't want it active feed imports outside of our plugin */
        remove_action( 'wp_feed_options', 'wprss_do_not_cache_feeds' );             
        
        //$feed = wprss_fetch_feed( $feed_url );                        
        remove_filter( 'wp_feed_cache_transient_lifetime' , 'wprss_feed_cache_lifetime' ); 

        if ( !is_wp_error( $feed ) ) {

            // Figure out how many total items there are, but limit it to 5. 
            $maxitems = $feed->get_item_quantity(5); 

            if ( $maxitems == 0 ) { return; }

            // Build an array of all the items, starting with element 0 (first element).
            $items = $feed->get_items( 0, $maxitems );   
            return $items;
        }

        else { return; }        
    }


    function wprss_items_insert_post( $items, $feed_ID ) {

        // Gather the permalinks of existing feed item's related to this feed source
        $existing_permalinks = get_existing_permalinks( $feed_ID );         

        foreach ( $items as $item ) {                        
            // Check if newly fetched item already present in existing feed items, 
            // if not insert it into wp_posts and insert post meta.
            if ( ! ( in_array( $item->get_permalink(), $existing_permalinks ) ) ) { 
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
                wprss_items_insert_post_meta( $inserted_ID, $item, $feed_ID );                   
            } 
        }       
    }    


    /**
     * Creates meta entries for feed items while they are being imported
     * 
     * @since 2.3
     */
    function wprss_items_insert_post_meta( $inserted_ID, $item, $feed_ID) {
        update_post_meta( $inserted_ID, 'wprss_item_permalink', $item->get_permalink() );
        update_post_meta( $inserted_ID, 'wprss_item_description', $item->get_description() );                        
        update_post_meta( $inserted_ID, 'wprss_item_date', $item->get_date( 'U' ) ); // Save as Unix timestamp format
        update_post_meta( $inserted_ID, 'wprss_feed_id', $feed_ID); 
        do_action( 'wprss_items_create_post_meta', $inserted_ID, $item, $feed_ID );
    } 


    add_action( 'wp_insert_post', 'wprss_fetch_insert_feed_items', '', 2 ); 
    /**
     * Fetches feed items from source provided and inserts into db
     * 
     * This function is used when inserting or untrashing a new feed source, it only gets feeds from that particular source
     * 
     * @since 3.0
     */
    function wprss_fetch_insert_feed_items( $post_id, $post ) {            

        // Only run the rest of the function if the post is a feed source and it has just been published       
        if( ( $post->post_type == 'wprss_feed' ) && ( $post->post_status == 'publish' ) ) { 
                    
            $feed_ID = $post_id;

            $feed_url = get_post_meta( $feed_ID, 'wprss_url', true );
            
            // Use the URL custom field to fetch the feed items for this source
            if( !empty( $feed_url ) ) {                                     
                $items = wprss_get_feed_items( $feed_url );                        
            }                

            if ( ! empty( $items ) ) {
                wprss_items_insert_post( $items, $feed_ID );
            } 
            wp_reset_postdata(); // Restore the $post global to the current post in the main query      
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
                
                $feed_ID = get_the_ID();
                $feed_url = get_post_meta( get_the_ID(), 'wprss_url', true );
                
                // Use the URL custom field to fetch the feed items for this source
                if( !empty( $feed_url ) ) {                                     
                    $items = wprss_get_feed_items( $feed_url );                        
                }                

                if ( ! empty( $items ) ) {
                    wprss_items_insert_post( $items, $feed_ID );
                } 
            }
            wp_reset_postdata(); // Restore the $post global to the current post in the main query        
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
     * Delete old feed items from the database to avoid bloat
     * 
     * @since 2.0
     */
    function wprss_truncate_posts() {
        global $wpdb;
        $general_settings = get_option( 'wprss_settings_general' );        

        if ( $general_settings['limit_feed_items'] == 0 ) {
            return;
        }

        // Set your threshold of max posts and post_type name
        $threshold = $general_settings['limit_feed_items'];
        $post_type = 'wprss_feed_item';

        // Query post type
        // $wpdb query allows me to select specific columns instead of grabbing the entire post object.
        $query = "
            SELECT ID, post_title FROM $wpdb->posts 
            WHERE post_type = '$post_type' 
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