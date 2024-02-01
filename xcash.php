<?php

require_once __DIR__.'/vendor/autoload.php';

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://yourwebsite.com
 * @since             1.0.0
 * @package           
 *
 * @wordpress-plugin
 * Plugin Name:       xCash
 * Description:       xCash payment gateway for WooCommerce
 * Version:           1.0.0
 * Author:            YourCompany
 * Author URI:        https://yourwebsite.com/xCash
 * Text Domain:       xCash
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Rename this for your plugin and update it as you release new versions.
 */

define('XCASH_API_ENDPOINT', 'https://script.viserlab.com/xcash');
define('XCASH_PLUGIN_VERSION', '1.0.0');
define('XCASH_PLUGIN_NAME', 'xCash');
define('XCASH_ROOT', plugin_dir_path(__FILE__));
define('XCASH_PLUGIN_URL', str_replace('index.php','',plugins_url( 'index.php', __FILE__ )));


function xCash_init()
{
	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

	include 'gateway/class-gateway.php';

	add_filter('woocommerce_payment_gateways', 'xCash_gateway');

	function xCash_gateway( $methods )
	{
		$methods[] = 'xCashGateway';
		return $methods;
	}
    
    function account_add_notice() 
    {
        echo '<div class="notice notice-warning">Please steup your '.XCASH_PLUGIN_NAME.' payment secret key, public key and others from <a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section='.strtolower(xcash_title_to_key(XCASH_PLUGIN_NAME)).'_gateway') . '">WooCommerce General Setting</a>.</div>';
    }

}

add_action( 'plugins_loaded', 'xCash_init' );