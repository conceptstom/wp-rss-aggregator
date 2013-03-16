<?php 
    /**
     * Scripts
     * 
     * @package WPRSSAggregator
     */ 


    add_action( 'admin_enqueue_scripts', 'wprss_admin_scripts_styles' ); 
    /**
     * Insert required scripts, styles and filters on the admin side
     * 
     * @since 2.0
     */   
    function wprss_admin_scripts_styles() {

        // Only load scripts if we are on this plugin's options or settings pages (admin)
        if ( isset( $_GET['page'] ) && ( $_GET['page'] == 'wprss-aggregator' || $_GET['page'] == 'wprss-aggregator-settings' 
            || $_GET['page'] == 'wprss-import-export-settings' || $_GET['page'] == 'wprss-debugging' ) ) {        
            wp_enqueue_style( 'styles', WPRSS_CSS . 'admin-styles.css' );                      
        } 

        // Only load scripts if we are on wprss_feed add post or edit post screens
        $screen = get_current_screen();

        if ( ( 'post' === $screen->base || 'edit' === $screen->base ) && ( 'wprss_feed' === $screen->post_type 
            || 'wprss_feed_item' === $screen->post_type ) ) {
            wp_enqueue_style( 'admin-styles', WPRSS_CSS . 'admin-styles.css' );
            wp_enqueue_script( 'admin-custom', WPRSS_JS .'admin-custom.js', array('jquery') );
            if ( 'post' === $screen->base && 'wprss_feed' === $screen->post_type ) {
                // Change text on post screen from 'Enter title here' to 'Enter feed name here'
                add_filter( 'enter_title_here', 'wprss_change_title_text' );
            }
        } 

        do_action( 'wprss_admin_scripts_styles' );
    } // end wprss_admin_scripts_styles


    add_action( 'wp_enqueue_scripts', 'wprss_load_scripts' );
    /**
     * Enqueues the required scripts.
     * 
     * @since 3.0
     */      
    function wprss_load_scripts() {                     
        wp_enqueue_script( 'jquery.colorbox-min', WPRSS_JS . 'jquery.colorbox-min.js', array( 'jquery' ) );         
        wp_enqueue_script( 'custom', WPRSS_JS . 'custom.js', array( 'jquery', 'jquery.colorbox-min' ) );  
        do_action( 'wprss_register_scripts' );         
    } // end wprss_head_scripts_styles


    /**
     * Returns the path to the WPRSS templates directory
     *
     * @since       3.0
     * @return      string
     */
    function wprss_get_templates_dir() {
        return WPRSS_DIR . 'templates';
    }


    /**
     * Returns the URL to the WPRSS templates directory
     *
     * @since       3.0
     * @return      string
     */
    function wprss_get_templates_uri() {
        return WPRSS_URI . 'templates';
    }


    add_action( 'wp_enqueue_scripts', 'wprss_register_styles' );
    /**
     * Register front end CSS styling files
     * Inspiration from Easy Digital Downloads
     * 
     * @since 3.0
     */  
    function wprss_register_styles() {   

        $general_settings = get_option( 'wprss_settings_general' );

        if( $general_settings['styles_disable'] == 1 )
            return;
        wp_enqueue_style( 'colorbox', WPRSS_CSS . 'colorbox.css', array(), '1.4.1' );     
        wp_enqueue_style( 'styles', WPRSS_CSS . 'styles.css', array(), '' );       

        /* If using DISABLE CSS option: 
        global $edd_options;

        if( isset( $edd_options['disable_styles'] ) )
            return;

        */

      /*  $file = 'wprss.css';

        // Check child theme first
        if ( file_exists( trailingslashit( get_stylesheet_directory() ) . 'wprss_templates/' . $file ) ) {
            $url = trailingslashit( get_stylesheet_directory_uri() ) . 'wprss_templates/' . $file;

        // Check parent theme next            
        } elseif ( file_exists( trailingslashit( get_template_directory() ) . 'wprss_templates/' . $file ) ) {
            $url = trailingslashit( get_template_directory_uri() ) . 'wprss_templates/' . $file;

        // Check theme compatibility last            
        } elseif ( file_exists( trailingslashit( wprss_get_templates_dir() ) . $file ) ) {
            $url = trailingslashit( wprss_get_templates_uri() ) . $file;
        }        

        wp_enqueue_style( 'wprss-styles', $url, WPRSS_VERSION );*/
    }
