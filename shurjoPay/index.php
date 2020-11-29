<?php
/**
 * Plugin Name: WooCommerce shurjoPay gateway
 * Plugin URI: http://shurjopay.com/
 * Description: Extends WooCommerce with shurjoPay gateway.
 * Version: 3.0.1
 * Author: shurjoMukhi
 * Author URI: http://shurjopay.com/
 * Text Domain: shurjopay
 */
defined('ABSPATH') OR exit('Direct access not allowed');
if (!defined('SHURJOPAY_PATH')) {
    define('SHURJOPAY_PATH', plugin_dir_path(__FILE__));
}

if (!defined('SHURJOPAY_URL')) {
    define('SHURJOPAY_URL', plugins_url('', __FILE__));
}


add_action('plugins_loaded', 'woocommerce_shurjopay_init', 0);
function woocommerce_shurjopay_init()
{
    if (!class_exists('WC_Payment_Gateway')) return;
    include_once 'classes/class-shurjopay-gateway.php';

    /**
     * Add the Gateway to WooCommerce
     * @param $methods
     * @return array
     */
    function woocommerce_add_shurjopay_gateway($methods)
    {
        $methods[] = 'WC_Shurjopay';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_shurjopay_gateway');
}

/**
 * -----------------------
 * Plugin Activation Hook
 * -----------------------
 */
register_activation_hook(__FILE__, 'pluginActivate');
function pluginActivate()
{
    update_option("shurjopay_version", "3.0.1");
}

/**
 * -------------------------
 * Plugin Deactivation Hook
 * -------------------------
 */
register_deactivation_hook(__FILE__, 'pluginDeactivate');
function pluginDeactivate()
{

}

/**
 * ----------------------
 * Plugin Uninstall Hook
 * ----------------------
 */
register_uninstall_hook(__FILE__, 'pluginUninstall');
function pluginUninstall()
{

}
