<?php
/**
 * Plugin Name: Gravity Forms Feed Forge
 * Description: Bulk process Gravity Forms feeds for existing entries with the power of Feed Forge. Works with most feed-based Gravity Forms add-ons (excluding payment add-ons).
 * Plugin URI: https://gravitywiz.com/gf-feed-forge/
 * Version: 1.1.15
 * Author: Gravity Wiz
 * Author URI: https://gravitywiz.com/
 * License: GPL2
 * Text Domain: gf-feed-forge
 * Domain Path: /languages
 *
 * Fork of Gravity Forms Utility by gravityplus (https://gravityplus.pro/gravity-forms-utility)
 *
 * This version has been reimagined by Gravity Wiz with fixes, an overhauled UI, and support for
 * processing feeds using Gravity Forms background processor.
 *
 * Gravity Forms Utility is originally licensed under GPL-2.0+, and this fork adheres to the same license terms.
 * See LICENSE file for more details.
 */
define( 'GWIZ_GF_FEED_FORGE_VERSION', '1.1.15' );

defined( 'ABSPATH' ) || die();

require plugin_dir_path( __FILE__ ) . 'vendor/autoload_packages.php';

\Spellbook\Bootstrap::register( __FILE__ );

add_action( 'gform_loaded', function() {
	if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
		return;
	}

	GFAddOn::register( 'GWiz_GF_Feed_Forge' );
}, 0 ); // Load before Gravity Flow

