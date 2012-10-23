<?php
/**
 * Plugin Name: MiniPress
 * Plugin URI: http://jumping-duck.com/wordpress/plugins/minipress
 * Description: Automatically concatenates and minifies all enqueued scripts upon pageload.
 * Version: 0.1
 * Author: Eric Mann
 * Author URI: http://eamann.com
 * License: GPLv2+
 */

/**
 * Copyright 2012  Eric Mann, Jumping Duck Media
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

require_once( 'lib/class.jsmin.php' );
require_once( 'lib/class.minipress.php' );

/**
 * Default initialization function
 * - Registers default textdomain.
 */
function minipress_init() {
	load_plugin_textdomain( 'minipress_translate', false, dirname( dirname( plugin_basename( __FILE__ ) ) ), '/lang/' );
}

function minipress_concat_scripts() {
	//script handles in head.
	$head_handles = MiniPress::get_queued_handles( array(
	                                                 'queue' => 'scripts',
	                                                 'location' => 'header',
	                                            ) );

	//script handles in footer.
	$footer_handles = MiniPress::get_queued_handles( array(
	                                                   'queue' => 'scripts',
	                                                   'location' => 'footer',
	                                              ) );

	$head_filename = MiniPress::concat_queued_files( array(
	                                               'queue' => 'scripts',
	                                               'handles' => $head_handles,
	                                          ) );

	$foot_filename = MiniPress::concat_queued_files( array(
	                                               'queue' => 'scripts',
	                                               'handles' => $footer_handles,
	                                          ) );

	if ( file_exists( "/cache/$foot_filename" ) ):
		wp_enqueue_script( 'cached-script-footer', "/cache/$foot_filename", '', '', true );

	endif;


	if ( file_exists( "/cache/$head_filename" ) ):
		wp_enqueue_script( 'cached-script-footer', "/cache/$head_filename", '', '' );

	endif;
}

// Wireup actions
add_action( 'init',               'minipress_init' );
add_action( 'wp_enqueue_scripts', 'minipress_concat_scripts', '99' );

function test_enqueue() {
	wp_enqueue_script( 'jquery' );
}
add_action( 'wp_enqueue_scripts', 'test_enqueue' );
?>