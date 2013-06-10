=== WP RSS Aggregator ===
Contributors: jeangalea
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=X9GP6BL4BLXBJ
Plugin URI: http://www.wprssaggregator.com
Tags: rss, feeds, aggregation, aggregator, import, feed aggregator, rss aggregator, multiple rss feeds, multi rss feeds, multi rss, rss import, feed import, feed import, multiple feed import, feed aggregation, multiple feeds, multi feed importer, multi feed import, multi import, autoblog, autoblogging, autoblogger
Requires at least: 3.3
Tested up to: 3.5.1
Stable tag: 3.1.1
Imports and aggregates multiple RSS Feeds using SimplePie. Outputs feeds sorted by date (latest first).

== Description ==

WP RSS Aggregator helps you create a feed reader/aggregator on your WordPress site. It works in a similar fashion to RSS readers like for example Google Reader.
You can add any number of feeds through an administration panel, the plugin will then pull all the feeds from these sites, merge them and sort them by date.

The plugin uses SimplePie for the feed operations. 
You can call the function from within the theme or even use a shortcode with parameters.

Since the plugin uses Custom Post Types to store the imported feeds, you are also free to display them in any way you want, in a similar fashion as you
would with other post types such as Posts or Pages.

= Demo =
The plugin can be seen in use on the [WPMayor.com WordPress News page](http://www.wpmayor.com/wordpress-news/)

= Credit = 
Created by Jean Galea from [WP Mayor](http://www.wpmayor.com)

= Translations =
Italian - Davide De Maestri

== Installation ==

1. Upload the `wp-rss-aggregator` folder to the `/wp-content/plugins/` directory
2. Activate the WP RSS Aggregator plugin through the 'Plugins' menu in WordPress
3. Configure the plugin by going to the `RSS Aggregator` menu item that appears in your dashboard menu.
3. Use the shortcode in your posts or pages: `[wp_rss_aggregator]`

The parameters accepted are:

* links_before
* links_after
* link_before
* link_after

An example of a shortcode with parameters:
`[wp_rss_aggregator link_before='<li class="feed-link">' link_after='</li>']`
It is advisable to use the 'HTML' view of the editor when inserting the shortcode with paramters.

An example of a function call from within the template files:
`
<?php 
wprss_display_feed_items( $args = array(
'links_before' => '<ul>',
'links_after' => '</ul>',
'link_before' => '<li>',
'link_after' => '</li>'
)); 
?>
`

OR 

`<?php do_shortcode('[wp-rss-aggregator]'); ?>`

You can also set whether the feed links should open in a new window, current window or even a lightbox, via the settings panel. 

The settings panel also has an option to set links as nofollow for SEO purposes.

Since version 2.0 you can also specify the number of feed items shown on the frontend.

== Frequently Asked Questions ==
= How can I output the feeds in my theme? =

You can either call the function directly within the theme:
`<?php wprss_display_feed_items(); ?>`

Or use the shortcode in your posts and pages:
[wp_rss_aggregator]

== Screenshots ==

1. The output of this plugin on the frontend, as seen on www.wpmayor.com.

2. Feed sources list.

3. Adding a new feed source.

4. Imported feeds.

5. Plugin settings page.

== Changelog ==

= Version 3.1.1 (2013-06-06) =
* Fixed bug: Incompatibility with some other plugins due to function missing namespace

= Version 3.1 (2013-06-06) =
* New feature: Option to set the number of feed items imported from every feed (default 5)
* New feature: Import and Export aggregator settings and feed sources
* New feature: Debugging page allowing manual feed refresh and feed reset
* Enhanced: Faster handling of restoring sources from trash when feed limit is 0
* Fixed bug: Limiter on number of overall feeds stored not working
* Fixed bug: Incompatibility issue with Foobox plugin fixed 
* Fixed bug: Duplicate feeds sometimes imported

= Version 3.0 (2013-03-16) =
* New feature: Option to select cron frequency
* New feature: Code extensibility added to be compatible with add-ons
* New feature: Option to set a limit to the number of feeds stored (previously 50, hard coded)
* New feature: Option to define the format of the date shown below each feed item
* New feature: Option to show or hide source of feed item
* New feature: Option to show or hide publish date of feed item
* New feature: Option to set text preceding publish date
* New feature: Option to set text preceding source of feed item
* New feature: Option to link title or not
* New feature: Limit of 5 items imported for each source instead of 10
* Enhanced: Performance improvement when publishing * New feeds in admin
* Enhanced: Query tuning for better performance
* Enhanced: Major code rewrite, refactoring and inclusion of hooks
* Enhanced: Updated Colorbox to v1.4.1
* Enhanced: Better security implementations	
* Enhanced: Better feed preview display
* Fixed bug: Deletion of items upon source deletion not working properly
* Requires: WordPress 3.3

= Version 2.2.3 (2012-11-01) =
* Fixed bug: Tab navigation preventing typing in input boxes
* Removed: Feeds showing up in internal linking pop up

= Version 2.2.2 (2012-10-30) =
* Removed: Feeds showing up in site search results
* Enhanced: Better tab button navigation when adding a new feed
* Enhanced: Better guidance when a feed URL is invalid

= Version 2.2.1 (2012-10-17) =
* Fixed bug: wprss_feed_source_order assumes everyone is an admin

= Version 2.2 (2012-10-01) =
* Italian translation added
* Feed source order changed to alphabetical
* Fixed bug - repeated entries when having a non-valid feed source
* Fixed bug - all imported feeds deleted upon trashing a single feed source

= Version 2.1 (2012-09-27) =
* Now localised for translations
* Fixed bug with date string
* Fixed $link_before and $link_after, now working
* Added backwards compatibility for wp_rss_aggregator() function

= Version 2.0 (2012-09-21) =
* Bulk of code rewritten and refactored
* Added install and upgrade functions
* Added DB version setting
* Feed sources now stored as Custom Post Types
* Feed source list sortable ascending or descending by name
* Removed days subsections in feed display
* Ability to limit total number of feeds displayed
* Feeds now fetched via Cron
* Cron job to delete old feed items, keeps max of 50 items in DB
* Now requires WordPress 3.2
* Updated colorbox to v1.3.20.1
* Limit of 15 items max imported for each source
* Fixed issue of page content displaying incorrectly after feeds


= Version 1.1 (2012-08-13) =
* Now requires WordPress 3.0
* More flexible fetching of images directory
* Has its own top level menu item
* Added settings section
* Ability to open in lightbox, new window or default browser behaviour
* Ability to set links as follow or no follow
* Using constants for oftenly used locations
* Code refactoring
* Changes in file and folder structure


= Version 1.0 (2012-01-06) =
* Initial release.