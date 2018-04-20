<?php

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
Plugin Name: Gravity Forms Help Scout Add-On
Plugin URI: https://www.gravityforms.com
Description: Integrates Gravity Forms with Help Scout, enabling end users to create new Help Scout conversations.
Version: 1.5
Author: rocketgenius
Author URI: https://www.rocketgenius.com
License: GPL-2.0+
Text Domain: gravityformshelpscout
Domain Path: /languages

------------------------------------------------------------------------
Copyright 2009-2016 rocketgenius
last updated: October 20, 2010

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 **/

define( 'GF_HELPSCOUT_VERSION', '1.5' );

// If Gravity Forms is loaded, bootstrap the Help Scout Add-On.
add_action( 'gform_loaded', array( 'GF_HelpScout_Bootstrap', 'load' ), 5 );

/**
 * Class GF_HelpScout_Bootstrap
 *
 * Handles the loading of the Help Scout Add-On and registers with the Add-On Framework.
 */
class GF_HelpScout_Bootstrap {

	/**
	 * If the Feed Add-On Framework exists, Help Scout Add-On is loaded.
	 *
	 * @access public
	 * @static
	 */
	public static function load() {

		if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
			return;
		}

		require_once( 'class-gf-helpscout.php' );

		GFAddOn::register( 'GFHelpScout' );

	}

}

/**
 * Returns an instance of the GFHelpScout class
 *
 * @see    GFHelpScout::get_instance()
 *
 * @return object GFHelpScout
 */
function gf_helpscout() {
	return GFHelpScout::get_instance();
}
