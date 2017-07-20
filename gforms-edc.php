<?php
/*
Plugin Name: Gravity Forms EDC Add-On
Plugin URI: http://www.gravityforms.com
Description: A simple add-on to demonstrate the use of the Add-On Framework
Version: 2.1
Author: Rocketgenius
Author URI: http://www.rocketgenius.com

------------------------------------------------------------------------
Copyright 2012-2016 Rocketgenius Inc.

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
*/

define( 'GF_EDC_ADDON_VERSION', '1.0' );

add_action( 'gform_loaded', array( 'GF_EDC_AddOn_Bootstrap', 'load' ), 5 );

class GF_EDC_AddOn_Bootstrap {

    public static function load() {

        if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
            return;
        }

        require_once 'class-gforms-edc.php';

        require_once 'vendor/mandrill/mandrill/src/Mandrill.php';

        GFAddOn::register( 'GFEdcAddOn' );
    }

}

function gf_edc_addon() {
    return GFEdcAddOn::get_instance();
}


/**
 * Add all Gravity Forms capabilities to Editor role.
 * Runs during plugin activation.
 * 
 * @access public
 * @return void
 */
function activate_gforms_edc() {
  
  $role = get_role( 'editor' );
  $role->add_cap( 'gform_full_access' );
}
// Register our activation hook
register_activation_hook( __FILE__, 'activate_gforms_edc' );

/**
 * Remove Gravity Forms capabilities from Editor role.
 * Runs during plugin deactivation.
 * 
 * @access public
 * @return void
 */
function deactivate_gforms_edc() {
 
 $role = get_role( 'editor' );
 $role->remove_cap( 'gform_full_access' );
}
// Register our de-activation hook
register_deactivation_hook( __FILE__, 'deactivate_gforms_edc' );
