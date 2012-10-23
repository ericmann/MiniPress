<?php
/**
 * Core functionality of the MiniPress dynamic minification plugin.
 *
 * @package MiniPress
 * @author Eric Mann <eric@eamann.com>
 * @copyright 2012 Eric Mann <eric@eamann.com>
 * @copyright 2012 Jumping Duck Media <info@jumping-duck.com>
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPL2+
 */
class MiniPress {
	public static function get_queued_handles( $args ) {
		$defaults = array (
			'queue'    => 'styles',
			'location' => 'header',
		);
		$args = wp_parse_args( $args, $defaults );

		//Determine which queue we're working with
		if ( $args['queue'] == 'scripts' ){
			global $wp_scripts;
			$queue = &$wp_scripts;
		} else {
			global $wp_styles;
			$queue = &$wp_styles;
		}

		//Get all the enqueued handles
		$queued = $queue->queue;

		//Set up the $handles array to reutrn
		$handles = array();

		//For each handle run through our exclusions check and put into concat array
		foreach ($queued as $k=>$handle) {
			/* Exclusions for All: */

			//Exclude if it doesn't start with http (i.e. relative path -- admin-bar.css does this.)
//			if ( substr( $queue->registered[$handle]->src, 0, 4 ) != 'http' )
//				continue;

			//Exclude if not from this domain
//			$domainlen = strlen( home_url() );
//			if ( substr( $queue->registered[$handle]->src, 0, $domainlen ) != home_url() )
//				continue;

			/* Exclusions for Styles */
			if ($args['queue'] == 'styles' ) {
				//Exclude if doesn't end in .css because it may be dynamic (uncacheable)
				if (substr( $queue->registered[$handle]->src, -3) != 'css' )
					continue;
			}


			/* Exclusions for Scripts */
//			if ($args['queue'] == 'scripts') {
				//if this is a footer script, and we're not in the footer, skip.
//				if ( isset($queue->registered[$handle]->extra['group']) && $args['location'] != 'footer')
//					continue;
//			}

			//If we didn't skip over this item, we can assume we need to concat this handle.
			$handles[] = $handle;
		}

		return $handles;
	}

	/**
	 * Concatenate queued files.
	 *
	 * Accepts an array of style or script handles that will be concatenated into a single file.
	 * Returns the concatenated file name.
	 *
	 * @param array $args
	 * @return string Filename hash.
	 */
	public static function concat_queued_files( $args ) {
		global $wp_filesystem;

		require_once( ABSPATH . 'wp-admin/includes/file.php' );

		// okay, let's see about getting credentials
		$url = wp_nonce_url('themes.php?page=otto','otto-theme-options');
		if (false === ($creds = request_filesystem_credentials($url, '', false, false, array( 'save' ) ) ) ) {
			// if we get here, then we don't have credentials yet,
			// but have just produced a form for the user to fill in,
			// so stop processing for now
			return true; // stop the normal page form from displaying
		}

		// now we have some credentials, try to get the wp_filesystem running
		if ( ! WP_Filesystem($creds) ) {
			// our credentials were no good, ask the user for them again
			request_filesystem_credentials($url, '', true, false, array( 'save' ) );
			return true;
		}

		$cache_dir = wp_upload_dir();

		$cache_dir = trailingslashit( $cache_dir['basedir'] ) . "cache";

		if ( ! $wp_filesystem->is_dir( $cache_dir ) ) {
			$wp_filesystem->mkdir( $cache_dir );
		}

		$defaults = array(
			'queue'   => 'styles',
			'handles' => array()
		);

		$args = wp_parse_args( $args, $defaults );

		// Determine which queue we're working with
		if ( $args['queue'] == 'scripts' ) {
			global $wp_scripts;
			$queue = &$wp_scripts;
			$ext = 'js';
		} else {
			global $wp_styles;
			$queue = &$wp_styles;
			$ext = 'css';
		}

		// Create concatenated filename
		$hash = '';

		foreach( $args['handles'] as $k=>$handle ) {
			$hash .= $handle . $queue->registered[$handle]->ver;
		}

		$hash = md5( $hash );

		$filename = "concat-$hash.$ext";

		if ( $wp_filesystem->exists( "$cache_dir/$filename" ) )
			return $filename;

		// Get the content to create our cached file
		$concatenated = '';

		foreach( $args['handles'] as $k=>$handle ) {
			$src = $queue->registered[$handle]->src;
			$ver = $queue->registered[$handle]->ver;
			$media = $queue->registered[$handle]->args;

			// If this is a relative file ...
			if ( substr( $src, 0, 1 ) == '/' ) {
				$src = home_url() . $src;
			}

			// Get the content of the file
			$content = $wp_filesystem->get_contents( $src );

			$concatenated .= $content;
		}

		$wp_filesystem->put_contents( "$cache_dir/$filename", $concatenated, FS_CHMOD_FILE );

		return $filename;
	}
}
