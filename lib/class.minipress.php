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
	private static $fs = false;

	/**
	 * Get a handle on the internal WP_Filesystem object so we can use it.
	 *
	 * @return bool|object WP_Filesystem class or false if unavailable.
	 */
	private static function get_filesystem() {
		if ( false === self::$fs ) {
			global $wp_filesystem;

			require_once( ABSPATH . 'wp-admin/includes/file.php' );

			// First, let's check to see if we have write access.  At the moment, only direct access and fopen/fwrite are supported.
			$write_method = get_filesystem_method( array(), false );
			if ( $write_method != 'direct' && $write_method != 'ftpsockets' ) {
				self::$fs = false;
				goto output;
			}

			if ( false === ( $creds = @request_filesystem_credentials( '' ) ) ) {
				// If we get here, we don't have credentials. Rather than trying to concatenate things when we can't save them
				// back to the filesystem, we just bail.
				self::$fs = false;
				goto output;
			}

			// Now we have some credentials, try to get the wp_filesystem running
			if ( ! WP_Filesystem( $creds ) ) {
				// Our credentials were no good, so bail.
				self::$fs = false;
				goto output;
			}

			self::$fs = $wp_filesystem;
		}

		output:
		return self::$fs;
	}

	/**
	 * Get a list of all file handles to be queued for concatentation.
	 *
	 * This function will run iteratively to process any registered script dependencies.
	 *
	 * @param object $queue    WP_Dependency queue from which to process information.
	 * @param string $handle   Name of the script/style to add to the concatenation queue.
	 * @param array  $handles  Array of script/style handles to be concatenated.
	 * @param string $type     Type of dependency to process - scripts or styles.
	 * @param string $location Location to print HTML output - header or footer.
	 */
	private static function queue_file( $queue, $handle, &$handles = array(), $type = 'scripts', $location = 'header' ) {
		/* Exclusions for Styles */
		if ($type == 'styles' ) {
			// Exclude if doesn't end in .css because it may be dynamic (uncacheable)
			if (substr( $queue->registered[$handle]->src, -3) != 'css' )
				return;
		}

		/* Exclusions for Scripts */
		if ($type == 'scripts') {
			// If this is a footer script, and we're not in the footer, skip.
			if ( isset( $queue->registered[$handle]->extra['group'] ) && $location != 'footer' ) {
				return;
			}

			if ( ! isset( $queue->registered[$handle]->extra['group'] ) && $location == 'footer' ) {
				return;
			}
		}

		// Handle any dependencies
		foreach ( $queue->registered[$handle]->deps as $dependency ) {
			self::queue_file( $queue, $dependency, $handles, $type, $location );
		}

		// If we didn't skip over this item, we can assume we need to concat this handle.
		$handles[] = $handle;
	}

	/**
	 * Add a single file to the concatenated output.
	 *
	 * @param object $queue             Queue from which to add the file.
	 * @param string $handle            Handle of the script/style to add to the concatenated output.
	 * @param string $concatenated      Concatenated output.
	 * @param array  $concatenated_list List of handles added to the concatenated output.
	 */
	private static function concat_file( $queue, $handle, &$concatenated = '', &$concatenated_list = array() ) {
		$src = $queue->registered[$handle]->src;
		$ver = $queue->registered[$handle]->ver;
		$media = $queue->registered[$handle]->args;

		// If this is a relative file ...
		if ( substr( $src, 0, 1 ) == '/' ) {
			$src = home_url() . $src;
		}

		// Get the content of the file
		$content = self::get_filesystem()->get_contents( $src );

		$concatenated .= $content;

		$concatenated_list[] = $handle;
	}

	/**
	 * Get an array of all scripts/styles queued in the header/footer.
	 *
	 * @param array $args Default arguments - 'queue' is either scripts or styles. 'location' is header or footer.
	 *
	 * @return array Queued handles
	 */
	private static function get_queued_handles( $args ) {
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

		// If nothing is queued, exit
		if ( null == $queue ) {
			return array();
		}

		//Get all the enqueued handles
		$queued = $queue->queue;

		//Set up the $handles array to reutrn
		$handles = array();

		//For each handle run through our exclusions check and put into concat array
		foreach  ($queued as $k => $handle ) {
			self::queue_file( $queue, $handle, $handles, $args['queue'], $args['location'] );
		}

		return $handles;
	}

	/**
	 * Remove all queued scripts because they're already included in the concatenated version.
	 *
	 * @param string $hash Hash of the concatenated files that need to be removed.
	 * @param string $type Either 'scripts' or 'styles.'
	 */
	private static function remove_queued_files( $hash, $type = 'scripts' ) {
		$handles = get_option( "minipress_$hash", array() );

		foreach( $handles as $handle ) {
			switch( $type ) {
				case 'scripts':
					wp_dequeue_script( $handle );
					break;
				case 'styles':
					wp_dequeue_style( $handle );
					break;
			}
		}
	}

	/**
	 * Concatenate queued files.
	 *
	 * Accepts an array of style or script handles that will be concatenated into a single file.
	 * Returns the concatenated file name.
	 *
	 * @param array $args
	 * @return bool|string Filename hash of false if something goes wrong.
	 */
	private static function concat_queued_files( $args ) {
		// If we can't use the filesystem, bail.
		if ( false === ( $filesystem = self::get_filesystem() ) )
			return false;

		$cache_dir = wp_upload_dir();

		$cache_dir = trailingslashit( $cache_dir['basedir'] ) . "cache";

		if ( ! $filesystem->is_dir( $cache_dir ) ) {
			$filesystem->mkdir( $cache_dir );
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

		if ( $filesystem->exists( "$cache_dir/$filename" ) )
			return $filename;

		// Get the content to create our cached file
		$concatenated = '';
		$concatenated_list = array();

		foreach( $args['handles'] as $k => $handle ) {
			self::concat_file( $queue, $handle, $concatenated, $concatenated_list );
		}

		update_option( "minipress_$hash", $concatenated_list );

		// If we're debugging, don't minify anything. Otherwise, minify all the things!
		if ( ! defined( 'SCRIPT_DEBUG' ) || SCRIPT_DEBUG == false ) {
			$concatenated = JSMin::minify( $concatenated );
		}

		$filesystem->put_contents( "$cache_dir/$filename", $concatenated, FS_CHMOD_FILE );

		return $filename;
	}


	/**
	 * Concatenate header scripts in the header and footer scripts in the footer.
	 */
	public static function concat_scripts() {
		// If we can't use the filesystem, bail.
		if ( false === ( $filesystem = self::get_filesystem() ) )
			return;

		$cache_dir = wp_upload_dir();

		$cache_dir = trailingslashit( $cache_dir['basedir'] ) . "cache";

		$cache_url = content_url( 'uploads/cache' );

		// If SCRIPT_DEBUG is turned on, don't do anything to scripts
		if ( ! defined( 'SCRIPT_DEBUG' ) || false == SCRIPT_DEBUG ) {
			//script handles in head.
			$head_handles = self::get_queued_handles(
				array(
				     'queue'    => 'scripts',
				     'location' => 'header',
				)
			);

			if ( count( $head_handles ) > 0 ) {
				$head_filename = self::concat_queued_files(
					array(
					     'queue'   => 'scripts',
					     'handles' => $head_handles,
					)
				);

				// Queue up the header scripts
				if ( $head_filename && $filesystem->exists( "$cache_dir/$head_filename" ) ) {
					$hash = substr( $head_filename, 7 );
					$hashes = explode( '.', $hash );
					$hash = $hashes[0];
					self::remove_queued_files( $hash, 'scripts' );
					wp_enqueue_script( 'cached-script-header', "$cache_url/$head_filename", '', '' );
				}
			}

			//script handles in footer.
			$footer_handles = self::get_queued_handles(
				array(
				     'queue'    => 'scripts',
				     'location' => 'footer',
				)
			);

			if ( count( $footer_handles ) > 0 ) {
				$foot_filename = self::concat_queued_files(
					array(
					     'queue'   => 'scripts',
					     'handles' => $footer_handles,
					)
				);

				// Queue up the footer scripts.
				if ( $foot_filename && $filesystem->exists( "$cache_dir/$foot_filename" ) ) {
					$hash = substr( $foot_filename, 7 );
					$hashes = explode( '.', $hash );
					$hash = $hashes[0];
					self::remove_queued_files( $hash, 'scripts' );
					wp_enqueue_script( 'cached-script-footer', "$cache_url/$foot_filename", '', '', true );
				}
			}
		}

		// If WP_DEBUG is turned on, don't do anything to styles
		if ( ! defined( 'WP_DEBUG' ) || false == WP_DEBUG ) {

		}
	}
}
?>