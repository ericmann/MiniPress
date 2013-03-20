<?php
/**
 * Plugin Name: MiniPress
 * Plugin URI: http://jumping-duck.com/wordpress/plugins/minipress
 * Description: Automatically concatenates and minifies all enqueued scripts upon pageload.
 * Version: 0.5
 * Author: Eric Mann
 * Author URI: http://eamann.com
 * License: GPLv2+
 */

/**
 * Copyright 2012-2013  Eric Mann, Jumping Duck Media
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
require_once( 'lib/class.compressor.php' );
require_once( 'lib/class.minipress.php' );

/**
 * Default initialization function
 * - Registers default textdomain.
 */
function minipress_init() {
	load_plugin_textdomain( 'minipress_translate', false, dirname( dirname( plugin_basename( __FILE__ ) ) ), '/lang/' );
}

// Wireup actions
add_action( 'init',               'minipress_init' );
add_action( 'wp_enqueue_scripts', array( 'MiniPress', 'concat_scripts' ), '99' );
add_action( 'wp_enqueue_scripts', array( 'MiniPress', 'concat_styles' ),  '99' );
?>