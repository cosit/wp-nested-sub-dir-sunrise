<?php
/*************************
 * Version of sunrise.php intended to allow for nested sub-directory WordPress sites on a single Multisite install already setup for sub-directories.  Works with multiple networks. Additional rules must be added to the root .htaccess file.  
 * Based on: 
 * http://www.paulund.co.uk/wordpress-multisite-nested-paths 
 * Works with the following Network Plugins:
 * Networks for WordPress (1.1.6)
   https://wordpress.org/plugins/networks-for-wordpress/)
 * Multi Networks Setup (1.0.0)
   https://wordpress.org/plugins/multi-networks-setup/
 * WP Multi Network (1.7.0) ~ Caution: This version of the plugin currently has an error where moving a site from one network to another will cause all the sites in the destination network, except the new network's root site, to be orphaned and not accessible from WordPress.
   https://wordpress.org/plugins/wp-multi-network/
 **************************/
if( defined( 'DOMAIN_CURRENT_SITE' ) && defined( 'PATH_CURRENT_SITE' ) ) {

    // Gets path of the url
    $url = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );    
    // Get domain for site comparison
    $dm_domain = preg_replace( '|^www\.|', '', $_SERVER[ 'HTTP_HOST' ]);
    // Defined in wp-config.php, '/'
    $path = PATH_CURRENT_SITE;
    // Strips out www and creates sanitized query for use in setting current_site later
    if( ( $nowww = preg_replace( '|^www\.|', '', $dm_domain ) ) != $dm_domain )
        $where = $wpdb->prepare( 'domain IN (%s,%s)', $dm_domain, $nowww );
    else
        $where = $wpdb->prepare( 'domain = %s', $dm_domain );  

    // Set current_site
    $current_site = $wpdb->get_row( "SELECT * from {$wpdb->site} WHERE $where LIMIT 0,1" );
    //$current_site->path = $url;

    // Explode and loop through each directory/sub-directory in the URL to find the correct site
    $patharray = (array) explode( '/', trim( $url, '/' ));
    $blogsearch = '';
    $pathsearch = '';
    // Loop through and create sanitized queries based on the sub-directories found
    if( count( $patharray )){
        foreach( $patharray as $pathpart ){
            $pathsearch .= '/'. $pathpart;
            $blogsearch .= $wpdb->prepare(" OR (domain = %s AND path = %s) ", $dm_domain, $pathsearch .'/' );
        }
    }
    /* Set current_blog based on domain and sub-directories found.
       It orders by pathlen so it doesn't just return the first sub-directory in case it's a sub sub directory.  I.e. /test2/test3 */
    $current_blog = $wpdb->get_row( $wpdb->prepare("SELECT *, LENGTH( path ) as pathlen FROM $wpdb->blogs WHERE domain = %s AND path = '/'", $dm_domain, $path) . $blogsearch .'ORDER BY pathlen DESC LIMIT 1');

    // Set variables used in WP front/backend
    $blog_id = $current_blog->blog_id;
    $public  = $current_blog->public;
    $site_id = $current_blog->site_id;

    // Update current_site with the root site's site name
    $current_site = pu_get_current_site_name( $current_site );

}

function pu_get_current_site_name( $current_site ) {
    global $wpdb;

    if (!isset($current_site))
        $current_site = new stdClass();

    $current_site->site_name = wp_cache_get( $current_site->id . ':current_site_name', "site-options" );
    if ( !$current_site->site_name ) {
        $current_site->site_name = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM $wpdb->sitemeta WHERE site_id = %d AND meta_key = 'site_name'", $current_site->id ) );
        if( $current_site->site_name == null )
            $current_site->site_name = ucfirst( $current_site->domain );
        wp_cache_set( $current_site->id . ':current_site_name', $current_site->site_name, 'site-options');
    }
    return $current_site;
}
