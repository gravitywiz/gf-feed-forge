<?php
/**
 * Plugin Name: Gravity Forms Feed Forge
 * Description: Bulk process Gravity Forms feeds for existing entries with the power of Feed Forge. Works with most feed-based Gravity Forms add-ons (excluding payment add-ons).
 * Plugin URI: https://gravitywiz.com/gf-feed-forge/
 * Version: 1.0.0
 * Author: Gravity Wiz
 * Author URI: https://gravitywiz.com/
 * License: GPL2
 * Text Domain: gf-feed-forge
 * Domain Path: /languages
 *
 * @package gf-feed-forge
 * @copyright Copyright (c) 2022, Gravity Wiz, LLC
 * @author Gravity Wiz <support@gravitywiz.com>
 * @license GPLv2
 * @link https://github.com/gravitywiz/gf-feed-forge
 */


define( 'GWIZ_GF_FEED_FORGE_VERSION', '1.0.0' );

defined( 'ABSPATH' ) || die();

add_action( 'gform_loaded', function() {
	if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
		return;
	}

	require plugin_dir_path( __FILE__ ) . 'class-gwiz-gf-feed-forge.php';

	GFAddOn::register( 'GWiz_GF_Feed_Forge' );
}, 0 ); // Load before Gravity Flow

/**
 * Returns an instance of the GWiz_GF_Feed_Forge class
 *
 * @see 1.0.0
 *
 * @return GWiz_GF_Code_Chest
 */
function gwiz_gf_feed_forge() {
	return GWiz_GF_Feed_Forge::get_instance();
}
